<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessCsvImport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CsvImportController extends Controller
{
    public function index()
    {
        $tables = collect(DB::select("
            SELECT tablename FROM pg_catalog.pg_tables
            WHERE schemaname='public' ORDER BY tablename
        "))->pluck('tablename');

        return view('csv-import', compact('tables'));
    }

    public function uploadChunk(Request $req)
    {
        $file = $req->file('chunk');
        $name = $req->name;
        $index = $req->index;

        $dir = storage_path("app/chunks/$name");
        if (!file_exists($dir))
            mkdir($dir, 0777, true);

        $file->move($dir, $index);
        return response()->json(['status' => 'chunk_ok']);
    }

    public function dispatchImport(Request $req)
    {
        $name = $req->name;
        $table = $req->table;

        // 1. Merge the chunks
        $chunkDir = storage_path("app/chunks/$name");
        $finalPath = storage_path("app/imports/" . Str::random(10) . ".csv"); // Unique name

        if (!file_exists(dirname($finalPath)))
            mkdir(dirname($finalPath), 0777, true);

        $out = fopen($finalPath, 'ab');
        // Sort chunks naturally (0, 1, 2, 10) not (0, 1, 10, 2)
        $chunks = glob("$chunkDir/*");
        natsort($chunks);

        foreach ($chunks as $chunk) {
            fwrite($out, file_get_contents($chunk));
            unlink($chunk); // Delete chunk after merge
        }
        fclose($out);
        rmdir($chunkDir); // Remove chunk dir

        // 2. Generate a Unique Import ID
        $importId = Str::uuid();

        // 3. Dispatch the Job (This returns immediately)
        ProcessCsvImport::dispatch($finalPath, $table, $importId);

        return response()->json([
            'status' => 'merged',
            'import_id' => $importId,
            'message' => 'File merged. Processing started in background.'
        ]);
    }

    public function checkStatus($id)
    {
        // Check cache for job status
        $status = Cache::get("import_status_{$id}");

        if (!$status) {
            return response()->json(['status' => 'pending', 'progress' => 0]);
        }

        return response()->json($status);
    }
}
