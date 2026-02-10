<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Architecture: High-Performance CSV Importer</title>
    <style>
        :root {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-code: #000000;
            --text-main: #e2e8f0;
            --text-muted: #94a3b8;
            --accent: #3b82f6; /* Blue */
            --success: #10b981; /* Green */
            --warning: #f59e0b; /* Orange */
            --danger: #ef4444; /* Red */
            --border: #334155;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            line-height: 1.7;
        }

        .layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        aside {
            background: #111827;
            border-right: 1px solid var(--border);
            padding: 2rem;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        aside h3 {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
        }

        aside ul {
            list-style: none;
            padding: 0;
        }

        aside li a {
            display: block;
            padding: 0.5rem 0;
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.95rem;
            transition: color 0.2s;
        }

        aside li a:hover { color: var(--accent); }

        /* Main Content */
        main {
            padding: 3rem 4rem;
            max-width: 1200px;
        }

        h1, h2, h3 { color: white; margin-top: 2.5rem; }
        h1 { font-size: 2.5rem; margin-top: 0; border-bottom: 1px solid var(--border); padding-bottom: 1rem; }
        h2 { font-size: 1.75rem; position: relative; padding-left: 1rem; border-left: 4px solid var(--accent); }

        p, li { color: var(--text-muted); font-size: 1.05rem; }

        /* Components */
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge.fe { background: #1e3a8a; color: #93c5fd; } /* Frontend */
        .badge.be { background: #3730a3; color: #c7d2fe; } /* Backend */
        .badge.db { background: #064e3b; color: #6ee7b7; } /* Database */

        /* Code Blocks */
        pre {
            background: var(--bg-code);
            padding: 1.5rem;
            border-radius: 8px;
            overflow-x: auto;
            border: 1px solid var(--border);
            margin: 1.5rem 0;
        }
        code { font-family: 'Fira Code', monospace; color: #e2e8f0; font-size: 0.9rem; }
        .keyword { color: #c678dd; }
        .func { color: #61afef; }
        .str { color: #98c379; }
        .comment { color: #5c6370; font-style: italic; }

        /* Diagrams */
        .diagram {
            background: var(--bg-card);
            border: 1px dashed var(--border);
            padding: 2rem;
            margin: 2rem 0;
            border-radius: 8px;
            text-align: center;
        }

        /* Alerts */
        .alert {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid var(--accent);
            padding: 1rem 1.5rem;
            margin: 1.5rem 0;
            border-radius: 0 4px 4px 0;
        }
        .alert-warning { border-color: var(--warning); background: rgba(245, 158, 11, 0.1); }

    </style>
</head>
<body>

<div class="layout">
    <aside>
        <h3>Documentation</h3>
        <ul>
            <li><a href="#overview">Overview</a></li>
            <li><a href="#chunking">1. Client-Side Chunking</a></li>
            <li><a href="#streaming">2. Stream Merging</a></li>
            <li><a href="#queue">3. Asynchronous Queue</a></li>
            <li><a href="#database">4. Batch Insertion</a></li>
            <li><a href="#bottlenecks">Common Bottlenecks</a></li>
        </ul>
    </aside>

    <main>
        <section id="overview">
            <h1>System Architecture</h1>
            <p>This document details the internal logic of the High-Performance CSV Import system. The architecture uses a <strong>De-coupled, Asynchronous Pipeline</strong> to bypass PHP's execution time and memory limits.</p>

            <div class="diagram">

                <svg width="100%" height="150" viewBox="0 0 800 150" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="50" y="50" width="100" height="50" rx="4" fill="#1e293b" stroke="#3b82f6" stroke-width="2"/>
                    <text x="100" y="80" text-anchor="middle" fill="#fff" font-size="12">Browser (JS)</text>

                    <path d="M150 75 L200 75" stroke="#475569" stroke-width="2" marker-end="url(#arrow)"/>

                    <rect x="200" y="50" width="120" height="50" rx="4" fill="#1e293b" stroke="#8b5cf6" stroke-width="2"/>
                    <text x="260" y="80" text-anchor="middle" fill="#fff" font-size="12">Controller (Merge)</text>

                    <path d="M320 75 L370 75" stroke="#475569" stroke-width="2" marker-end="url(#arrow)"/>

                    <rect x="370" y="50" width="100" height="50" rx="4" fill="#1e293b" stroke="#10b981" stroke-width="2"/>
                    <text x="420" y="80" text-anchor="middle" fill="#fff" font-size="12">Redis/DB Queue</text>

                    <path d="M470 75 L520 75" stroke="#475569" stroke-width="2" marker-end="url(#arrow)"/>

                    <rect x="520" y="50" width="120" height="50" rx="4" fill="#1e293b" stroke="#f59e0b" stroke-width="2"/>
                    <text x="580" y="80" text-anchor="middle" fill="#fff" font-size="12">Worker Process</text>
                </svg>
            </div>
        </section>

        <section id="chunking">
            <h2>1. Client-Side Chunking <span class="badge fe">Frontend</span></h2>
            <p>To bypass Nginx/Apache <code>client_max_body_size</code> limits, we do not send the file as a single HTTP request. We utilize the HTML5 <code>Blob.prototype.slice()</code> method.</p>

            <h3>Key Implementation Details:</h3>
            <ul>
                <li><strong>Byte-Range Requests:</strong> The file is split logically in RAM (pointers), not physically duplicated.</li>
                <li><strong>Sequential Execution:</strong> Chunks are uploaded one by one (<code>await fetch()</code>) to prevent flooding the server with concurrent connections, which could trigger DoS protection.</li>
                <li><strong>Failure Handling:</strong> Since chunks are independent, a failed chunk can be retried without re-uploading the entire file (future enhancement).</li>
            </ul>

<pre><code><span class="comment">// JavaScript Logic</span>
<span class="keyword">const</span> chunkSize = 2 * 1024 * 1024; <span class="comment">// 2MB</span>
<span class="keyword">const</span> chunk = file.slice(offset, offset + chunkSize);

<span class="comment">// Sends: Header "Content-Range: bytes 0-2097151/50000000"</span>
await uploadChunk(chunk);</code></pre>
        </section>

        <section id="streaming">
            <h2>2. Stream Merging <span class="badge be">Backend</span></h2>
            <p>Once all chunks arrive, we must reconstruct the original file. A naive approach would be <code>file_get_contents()</code>, which loads the entire file into RAM. <strong>We avoid this.</strong></p>

            <h3>The "Append" Strategy</h3>
            <p>We use PHP Streams with the <code>ab</code> (Append Binary) mode. This acts as a pipeline: data flows from the chunk file on disk directly to the target file on disk, bypassing the PHP memory limit entirely.</p>

<pre><code><span class="comment">// PHP Logic: Constant O(1) Memory Usage</span>
$target = fopen($finalPath, <span class="str">'ab'</span>); <span class="comment">// 'a' = Append, 'b' = Binary safe</span>

foreach ($chunks as $chunk) {
    $source = fopen($chunk, <span class="str">'rb'</span>);
    stream_copy_to_stream($source, $target); <span class="comment">// Pipes data directly</span>
    fclose($source);
    unlink($chunk); <span class="comment">// Immediate cleanup</span>
}
fclose($target);</code></pre>
        </section>

        <section id="queue">
            <h2>3. Asynchronous Queue <span class="badge be">Architecture</span></h2>
            <p>The HTTP request lifecycle ends immediately after merging. We dispatch a <code>Job</code> to the queue. This prevents the browser from timing out (HTTP 504 Gateway Timeout).</p>

            <div class="alert">
                <strong>Why this matters:</strong> A web server process (Apache/FPM) is designed to live for ~60 seconds. A CSV import might take 30 minutes. The Queue Worker is a CLI process designed to run indefinitely.
            </div>

            <ul>
                <li><strong>Driver:</strong> Database (for simplicity) or Redis (for high throughput).</li>
                <li><strong>Serialization:</strong> The Job class is serialized into a JSON string and stored in the <code>jobs</code> table.</li>
                <li><strong>CLI Command:</strong> <code>php artisan queue:work --timeout=3600</code>. The timeout flag is crucial; it overrides the default 60s limit for this specific worker.</li>
            </ul>
        </section>

        <section id="database">
            <h2>4. Batch Insertion <span class="badge db">Database</span></h2>
            <p>Inserting 1 million rows one-by-one results in 1 million network round-trips to the database. This is the primary bottleneck in most import systems.</p>

            <h3>Optimization Strategy:</h3>
            <ol>
                <li><strong>Generator:</strong> We use <code>league/csv</code> as an Iterator. It keeps only 1 row in memory at a time.</li>
                <li><strong>Batching:</strong> We buffer rows into an array. When the array hits 1,000 items, we fire <strong>one</strong> SQL query.</li>
                <li><strong>Transaction:</strong> (Optional) Wrapping batches in transactions ensures data integrity, though <code>insertOrIgnore</code> is often preferred for logs.</li>
            </ol>

<pre><code><span class="comment">// The "Batch" Pattern</span>
$batch = [];
foreach ($csv->getRecords() as $row) {
    $batch[] = $row;

    <span class="comment">// Buffer full? Flush to DB.</span>
    if (count($batch) === 1000) {
        DB::table('users')->insertOrIgnore($batch);
        $batch = []; <span class="comment">// Free memory</span>
    }
}</code></pre>
        </section>

        <section id="bottlenecks">
            <h2>Common Bottlenecks & Fixes</h2>

            <h3>1. Memory Exhaustion (Error 500)</h3>
            <p><strong>Cause:</strong> Loading the whole CSV into a variable (<code>$data = file($path)</code>).</p>
            <p><strong>Fix:</strong> Use <code>fopen()</code> and read line-by-line or use the Iterator pattern shown above.</p>

            <h3>2. Database Locks</h3>
            <p><strong>Cause:</strong> A single transaction wrapping 1 million inserts locks the table for too long.</p>
            <p><strong>Fix:</strong> Use "micro-transactions" (one per batch of 1000) or <code>insertOrIgnore</code> to avoid locking the table for read operations.</p>

            <h3>3. UUID Conflicts</h3>
            <p><strong>Cause:</strong> PostgreSQL strictly enforces types. A string UUID cannot be inserted into an <code>Auto-Increment Integer</code> ID column.</p>
            <p><strong>Fix:</strong> Update migration to <code>$table->uuid('id')</code> or exclude the ID column from the import to let the DB auto-generate it.</p>
        </section>

    </main>
</div>

</body>
</html>
