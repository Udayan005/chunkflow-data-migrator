<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>High Performance CSV Importer</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #3b82f6;
            --bg: #0f172a;
            --card: #1e293b;
            --text: #e2e8f0;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            width: 100%;
            max-width: 600px;
            padding: 20px;
        }

        .card {
            background: var(--card);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        h2 {
            margin-top: 0;
            font-weight: 700;
            color: white;
            text-align: center;
        }

        .dropzone {
            border: 2px dashed #475569;
            border-radius: 0.5rem;
            padding: 3rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            margin-bottom: 1.5rem;
            background: rgba(255, 255, 255, 0.02);
        }

        .dropzone:hover,
        .dropzone.active {
            border-color: var(--primary);
            background: rgba(59, 130, 246, 0.1);
        }

        .dropzone p {
            margin: 0;
            color: #94a3b8;
            pointer-events: none;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .select2-container .select2-selection--single {
            height: 45px;
            background: #334155;
            border: 1px solid #475569;
            color: white;
            display: flex;
            align-items: center;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: white;
        }

        .btn-action {
            background: var(--primary);
            color: white;
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-action:hover {
            background: #2563eb;
        }

        .btn-action:disabled {
            background: #475569;
            cursor: not-allowed;
        }

        /* Progress Bars */
        .progress-wrapper {
            margin-top: 20px;
            display: none;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .progress-track {
            background: #334155;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #10b981;
            width: 0%;
            transition: width 0.3s ease;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-uploading {
            color: #60a5fa;
        }

        .status-processing {
            color: #fbbf24;
        }

        .status-success {
            color: #34d399;
        }

        .status-error {
            color: #f87171;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="card">
            <h2>🚀 Data Importer</h2>

            <div class="dropzone" id="dropzone">
                <p>Drag & Drop CSV file here</p>
                <input type="file" id="fileInput" accept=".csv" hidden>
            </div>

            <div class="form-group">
                <select id="tables" style="width: 100%;">
                    <option></option>
                    @foreach ($tables as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            <button id="startBtn" class="btn-action">Start Import</button>

            <div class="progress-wrapper" id="progressArea">
                <div class="progress-info">
                    <span id="stepLabel">Starting...</span>
                    <span id="percentageLabel">0%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" id="progressBar"></div>
                </div>
                <div style="margin-top: 10px; font-size: 0.85rem; color: #94a3b8;" id="detailsLabel">Waiting for
                    action...</div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tables').select2({
                placeholder: "Select Database Table",
                allowClear: true
            });
        });

        const dz = document.getElementById('dropzone');
        const input = document.getElementById('fileInput');
        const btn = document.getElementById('startBtn');

        // Dropzone Events
        dz.onclick = () => input.click();
        dz.ondragover = e => {
            e.preventDefault();
            dz.classList.add('active');
        };
        dz.ondragleave = () => dz.classList.remove('active');
        dz.ondrop = e => {
            e.preventDefault();
            dz.classList.remove('active');
            input.files = e.dataTransfer.files;
            updateFileLabel();
        };
        input.onchange = () => updateFileLabel();

        function updateFileLabel() {
            if (input.files.length) dz.querySelector('p').innerText = "📂 " + input.files[0].name;
        }

        btn.onclick = async () => {
            const file = input.files[0];
            const table = $('#tables').val();

            if (!file || !table) {
                Swal.fire({
                    title: "Please select a file and a table.",
                    icon: "error",
                });
            }

            // Reset UI
            $('#progressArea').fadeIn();
            btn.disabled = true;
            updateStatus('Uploading...', 0, 'status-uploading');

            // --- STEP 1: CHUNK UPLOAD ---
            const chunkSize = 2 * 1024 * 1024; // 2MB
            const totalChunks = Math.ceil(file.size / chunkSize);

            try {
                for (let i = 0; i < totalChunks; i++) {
                    const chunk = file.slice(i * chunkSize, (i + 1) * chunkSize);
                    const fd = new FormData();
                    fd.append('chunk', chunk);
                    fd.append('index', i);
                    fd.append('name', file.name);

                    await fetch('/upload-chunk', {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });

                    // Update Upload Progress
                    const percent = Math.round(((i + 1) / totalChunks) * 100);
                    updateStatus('Uploading File...', percent, 'status-uploading');
                    document.getElementById('detailsLabel').innerText =
                        `Uploaded chunk ${i+1} of ${totalChunks}`;
                }

                // --- STEP 2: MERGE & DISPATCH JOB ---
                updateStatus('Merging & Queuing...', 100, 'status-processing');

                const mergeRes = await fetch('/merge-file', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        name: file.name,
                        table: table
                    })
                });

                const mergeData = await mergeRes.json();

                if (mergeData.import_id) {
                    // --- STEP 3: POLL STATUS ---
                    pollStatus(mergeData.import_id);
                }

            } catch (e) {
                console.error(e);
                updateStatus('Error', 100, 'status-error');
                document.getElementById('detailsLabel').innerText = "Network or Server Error occurred.";
                btn.disabled = false;
            }
        };

        function updateStatus(step, percent, colorClass) {
            document.getElementById('stepLabel').innerText = step;
            document.getElementById('percentageLabel').innerText = percent + "%";
            document.getElementById('progressBar').style.width = percent + "%";

            // Change color based on stage
            const bar = document.getElementById('progressBar');
            bar.style.backgroundColor = step.includes('Error') ? '#f87171' : (percent === 100 && step.includes(
                    'Success') ?
                '#34d399' : '#3b82f6');
        }

        function pollStatus(importId) {
            const interval = setInterval(async () => {
                const res = await fetch(`/import-status/${importId}`);
                const data = await res.json();

                if (data.status === 'processing') {
                    updateStatus('Processing Data...', data.progress, 'status-processing');
                    document.getElementById('detailsLabel').innerText = data.details;
                } else if (data.status === 'completed') {
                    clearInterval(interval);
                    updateStatus('Success!', 100, 'status-success');
                    document.getElementById('detailsLabel').innerText = data.details;
                    btn.disabled = false;


                    Swal.fire({
                        title: "Import Completed Successfully!",
                        icon: "success",
                    });

                } else if (data.status === 'failed') {
                    clearInterval(interval);
                    updateStatus('Failed', 100, 'status-error');
                    document.getElementById('detailsLabel').innerText = data.details;
                    btn.disabled = false;
                }
            }, 2000); // Poll every 2 seconds
        }
    </script>

</body>

</html>
