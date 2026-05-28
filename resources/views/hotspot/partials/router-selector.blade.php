<!-- Router Selector Component (Like Mikhmon) -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <div class="row align-items-center">
            <div class="col-auto">
                <strong><i class="bi bi-router"></i> Router:</strong>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" id="routerSelector" onchange="changeHotspotRouter(this.value)" style="min-width: 250px;">
                    @if(session('hotspot_router_mode') === 'all')
                        <option value="all" selected>ALL ROUTER</option>
                    @endif
                    
                    @foreach(\App\Models\Router::where('is_active', true)->get() as $r)
                        <option value="{{ $r->id }}" 
                                {{ session('hotspot_router_id') == $r->id ? 'selected' : '' }}>
                            {{ $r->name }} ({{ $r->ip_address }})
                        </option>
                    @endforeach
                    
                    @if(session('hotspot_router_mode') !== 'all')
                        <option value="all">ALL ROUTER</option>
                    @endif
                </select>
            </div>
            
            @if(isset($router) && $router)
                <div class="col-auto">
                    <span class="badge bg-success">
                        <i class="bi bi-check-circle"></i> Connected
                    </span>
                </div>
                <div class="col-auto text-muted small">
                    {{ $router->name }} - {{ $router->ip_address }}
                </div>
            @endif
            
            <div class="col text-end">
                <a href="{{ route('hotspot.change-router') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-repeat"></i> Change Router
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function changeHotspotRouter(value) {
    // ✅ OPTIMASI: Tampilkan loading indicator
    var loadingOverlay = document.createElement('div');
    loadingOverlay.id = 'routerLoadingOverlay';
    loadingOverlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;';
    loadingOverlay.innerHTML = '<div style="background:white;padding:20px;border-radius:10px;text-align:center;"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Mengganti router...</p></div>';
    document.body.appendChild(loadingOverlay);
    
    if (value === 'all') {
        // Set mode ALL ROUTER
        fetch('{{ route("hotspot.set-session") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                mode: 'all'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                document.body.removeChild(loadingOverlay);
                alert('Gagal mengganti router');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.body.removeChild(loadingOverlay);
            alert('Gagal mengganti router: ' + error.message);
        });
    } else {
        // Set specific router
        fetch('{{ route("hotspot.set-session") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                router_id: value 
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                document.body.removeChild(loadingOverlay);
                alert('Gagal mengganti router');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.body.removeChild(loadingOverlay);
            alert('Gagal mengganti router: ' + error.message);
        });
    }
}
</script>
@endpush

