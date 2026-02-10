<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use League\Csv\Reader;
use Throwable;

class ProcessCsvImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // Allow job to run for 1 hour
    protected $filePath;
    protected $tableName;
    protected $importId;

    public function __construct($filePath, $tableName, $importId)
    {
        $this->filePath = $filePath;
        $this->tableName = $tableName;
        $this->importId = $importId;
    }

    public function handle()
    {
        $cacheKey = "import_status_{$this->importId}";

        try {
            // Update status: Starting
            Cache::put($cacheKey, ['status' => 'processing', 'progress' => 0, 'details' => 'Initializing...'], 3600);

            if (!file_exists($this->filePath)) {
                throw new \Exception("File not found at {$this->filePath}");
            }

            // 1. Count Total Rows (for progress bar) - optimized for Linux/Unix
            // If on Windows, use a PHP loop to count lines instead.
            $lineCount = 0;
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $lineCount = intval(shell_exec("wc -l < " . escapeshellarg($this->filePath)));
            } else {
                // Fallback for Windows
                $lineCount = count(file($this->filePath));
            }
            // Subtract header
            $totalRows = $lineCount - 1;

            // 2. Setup CSV Reader
            $csv = Reader::createFromPath($this->filePath, 'r');
            $csv->setHeaderOffset(0);

            $columns = Schema::getColumnListing($this->tableName);

            $batch = [];
            $batchSize = 2000; // Optimal size for inserts
            $processedCount = 0;

            DB::disableQueryLog();

            // 3. Process Rows
            foreach ($csv->getRecords() as $offset => $row) {

                // Filter columns to match DB
                $filtered = array_intersect_key($row, array_flip($columns));

                if (!empty($filtered)) {
                    $batch[] = $filtered;
                }

                // Insert Batch
                if (count($batch) >= $batchSize) {
                    DB::table($this->tableName)->insertOrIgnore($batch);
                    $processedCount += count($batch);
                    $batch = [];

                    // Update Progress in Cache
                    $percentage = $totalRows > 0 ? round(($processedCount / $totalRows) * 100) : 0;
                    Cache::put($cacheKey, [
                        'status' => 'processing',
                        'progress' => $percentage,
                        'details' => "Processed $processedCount / $totalRows rows"
                    ], 3600);
                }
            }

            // Insert Remaining
            if (!empty($batch)) {
                DB::table($this->tableName)->insertOrIgnore($batch);
                $processedCount += count($batch);
            }

            // Cleanup
            @unlink($this->filePath);

            // Final Success Status
            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'details' => "Success! Imported $processedCount rows."
            ], 3600);

        } catch (Throwable $e) {
            // Report Failure
            Cache::put($cacheKey, [
                'status' => 'failed',
                'progress' => 0,
                'details' => "Error: " . $e->getMessage()
            ], 3600);

            // Re-throw to fail the job in Laravel's eyes too
            throw $e;
        }
    }
}
