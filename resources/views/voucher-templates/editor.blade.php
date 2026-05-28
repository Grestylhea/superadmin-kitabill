@extends('layouts.admin')

@section('title', 'Template Editor')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">
                <i class="fas fa-edit"></i> Template Editor
                <small class="text-muted">Customize voucher print template</small>
            </h4>
        </div>
        <a href="{{ isset($router) ? route('hotspot.dashboard', $router) : route('hotspot.users') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <!-- Template Editor -->
        <div class="col-lg-9">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-code"></i> Template Editor
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('voucher-templates.save-from-editor') }}" id="templateForm" onsubmit="return saveTemplate(event)">
                        @csrf
                        <input type="hidden" name="template" value="{{ $templateType }}">
                        <input type="hidden" name="router" value="{{ $routerId }}">
                        
                        <!-- Action Buttons -->
                        <div class="mb-3">
                            <div class="row">
                                <div class="col-md-4">
                                    <select class="form-select" name="template_select" id="templateSelect" onchange="changeTemplate()">
                                        <option value="default" {{ $templateType == 'default' ? 'selected' : '' }}>Default</option>
                                        <option value="small" {{ $templateType == 'small' ? 'selected' : '' }}>Small</option>
                                    </select>
                                </div>
                                <div class="col-md-8 text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save
                                    </button>
                                    <a href="{{ route('voucher-templates.preview', ['template' => $templateType, 'qr' => 'no', 'router' => $routerId]) }}" 
                                       target="_blank" 
                                       class="btn btn-success">
                                        <i class="fas fa-eye"></i> Preview
                                    </a>
                                    <a href="{{ route('voucher-templates.preview', ['template' => $templateType, 'qr' => 'yes', 'router' => $routerId]) }}" 
                                       target="_blank" 
                                       class="btn btn-danger">
                                        <i class="fas fa-qrcode"></i> Preview QR
                                    </a>
                                    <button type="button" class="btn btn-warning" onclick="resetTemplate()">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                    <a href="{{ route('voucher-templates.index') }}" class="btn btn-info">
                                        <i class="fas fa-list"></i> List Templates
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Code Editor -->
                        <div class="mb-3">
                            <textarea class="form-control font-monospace" 
                                      id="templateEditor" 
                                      name="html_content" 
                                      rows="25" 
                                      style="font-size: 12px;">{{ old('html_content', $template->html_content ?? '') }}</textarea>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Variables Panel -->
        <div class="col-lg-3">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Variable
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Logo</strong><br>
                                <small class="text-muted font-monospace">&lt;img src="<?= $logo; ?>" style="height: 30px; border:0;"&gt;</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="insertVariable('&lt;img src=\"<?= $logo; ?>\" style=\"height: 30px; border:0;\"&gt;')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Hotspotname</strong><br>
                                <small class="text-muted font-monospace">&lt;?= $hotspotname; ?&gt;</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="insertVariable('<?= $hotspotname; ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Username</strong><br>
                                <small class="text-muted font-monospace">&lt;?= $username; ?&gt;</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="insertVariable('<?= $username; ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Password</strong><br>
                                <small class="text-muted font-monospace">&lt;?= $password; ?&gt;</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="insertVariable('<?= $password; ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Validity</strong><br>
                                <small class="text-muted font-monospace">&lt;?= $validity; ?&gt;</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="insertVariable('<?= $validity; ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Time Limit</strong><br>
                                <small class="text-muted font-monospace">&lt;?= $timelimit; ?&gt;</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="insertVariable('<?= $timelimit; ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Data Limit</strong><br>
                                <small class="text-muted font-monospace">&lt;?= $datalimit; ?&gt;</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="insertVariable('<?= $datalimit; ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Price</strong><br>
                                <small class="text-muted font-monospace">&lt;?= $price; ?&gt;</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="insertVariable('<?= $price; ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Profile</strong><br>
                                <small class="text-muted font-monospace">&lt;?= $profile; ?&gt;</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="insertVariable('<?= $profile; ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Comment</strong><br>
                                <small class="text-muted font-monospace">&lt;?= $comment; ?&gt;</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="insertVariable('<?= $comment; ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>DNS Name</strong><br>
                                <small class="text-muted font-monospace">&lt;?= $dnsname; ?&gt;</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="insertVariable('<?= $dnsname; ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>QR Code</strong><br>
                                <small class="text-muted font-monospace">&lt;?= $qrcode ?&gt;</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" onclick="insertVariable('<?= $qrcode ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/material.min.css">
<style>
.CodeMirror {
    border: 1px solid #ddd;
    height: 600px;
    font-size: 12px;
}
.list-group-item {
    padding: 0.75rem 0;
}
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
<script>
    // Initialize CodeMirror
    var editor = CodeMirror.fromTextArea(document.getElementById('templateEditor'), {
        lineNumbers: true,
        mode: 'application/x-httpd-php',
        indentUnit: 4,
        indentWithTabs: true,
        lineWrapping: true,
        theme: 'material',
        matchBrackets: true,
        value: document.getElementById('templateEditor').value // Ensure initial value is set
    });
    
    // Log initial content for debugging
    console.log('CodeMirror initialized with content length:', editor.getValue().length);
    console.log('First 100 chars:', editor.getValue().substring(0, 100));

    function insertVariable(variable) {
        var cursor = editor.getCursor();
        editor.replaceRange(variable, cursor);
        editor.focus();
    }

    function changeTemplate() {
        var templateType = document.getElementById('templateSelect').value;
        window.location.href = '{{ route("voucher-templates.editor") }}?template=' + templateType + '&router={{ $routerId }}';
    }

    // IMPORTANT: Sync CodeMirror value back to textarea before form submit
    // CodeMirror replaces the textarea, so we MUST sync before submit
    function saveTemplate(event) {
        // Sync CodeMirror value to textarea
        editor.save();
        
        // Double-check: Get value from CodeMirror and set to textarea explicitly
        var editorValue = editor.getValue();
        var textarea = document.getElementById('templateEditor');
        textarea.value = editorValue;
        
        // Log to console for debugging
        console.log('=== SAVE TEMPLATE ===');
        console.log('CodeMirror content length:', editorValue.length);
        console.log('Textarea content length:', textarea.value.length);
        console.log('First 200 chars:', editorValue.substring(0, 200));
        
        // Verify they match
        if (textarea.value !== editorValue) {
            console.error('ERROR: Textarea and CodeMirror values do not match!');
            textarea.value = editorValue; // Force sync again
        }
        
        // Allow form to submit normally
        return true;
    }
    
    // Also sync on form submit as backup (before actual submit)
    document.getElementById('templateForm').addEventListener('submit', function(e) {
        // Sync before submit
        editor.save();
        var editorValue = editor.getValue();
        document.getElementById('templateEditor').value = editorValue;
        console.log('Form submit: Synced CodeMirror to textarea, length:', editorValue.length);
    }, true); // Use capture phase to run before other handlers

    // Preview already handled by link buttons

    function resetTemplate() {
        if (confirm('Reset template ke default? Perubahan yang belum disimpan akan hilang.')) {
            window.location.href = '{{ route("voucher-templates.reset-template") }}?template={{ $templateType }}&router={{ $routerId }}';
        }
    }
</script>
@endpush

