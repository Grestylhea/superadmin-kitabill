@extends('layouts.admin')

@section('content')
<div class="container-fluid">

    <h3 class="mb-4">Fiber Test Results</h3>

    {{-- Statistics --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm p-3">
                <div class="text-muted">Total Tests</div>
                <h4>{{ $stats['total_tests'] }}</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm p-3">
                <div class="text-muted">Passed</div>
                <h4 class="text-success">{{ $stats['passed'] }}</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm p-3">
                <div class="text-danger">Failed</div>
                <h4>{{ $stats['failed'] }}</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm p-3">
                <div class="text-warning">Warnings</div>
                <h4>{{ $stats['warnings'] }}</h4>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="card shadow-sm p-3 mb-4">
        <div class="row">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search technician / segment" value="{{ request('search') }}">
            </div>

            <div class="col-md-3">
                <select name="test_type" class="form-control">
                    <option value="">All Test Types</option>
                    <option value="OTDR" {{ request('test_type') === 'OTDR' ? 'selected' : '' }}>OTDR</option>
                    <option value="Power Meter" {{ request('test_type') === 'Power Meter' ? 'selected' : '' }}>Power Meter</option>
                    <option value="Light Source" {{ request('test_type') === 'Light Source' ? 'selected' : '' }}>Light Source</option>
                </select>
            </div>

            <div class="col-md-3">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="pass" {{ request('status') === 'pass' ? 'selected' : '' }}>Pass</option>
                    <option value="fail" {{ request('status') === 'fail' ? 'selected' : '' }}>Fail</option>
                    <option value="warning" {{ request('status') === 'warning' ? 'selected' : '' }}>Warning</option>
                </select>
            </div>

            <div class="col-md-3">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Segment</th>
                        <th>Core</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Loss (dB)</th>
                        <th>Technician</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($testResults as $test)
                        <tr>
                            <td>{{ $test->test_date }}</td>
                            <td>{{ $test->fiberCore->cableSegment->name ?? '-' }}</td>
                            <td>#{{ $test->fiberCore->core_number ?? '-' }}</td>
                            <td>{{ $test->test_type }}</td>
                            <td>
                                @if($test->status == 'pass')
                                    <span class="badge bg-success">Pass</span>
                                @elseif($test->status == 'fail')
                                    <span class="badge bg-danger">Fail</span>
                                @else
                                    <span class="badge bg-warning">Warning</span>
                                @endif
                            </td>
                            <td>{{ $test->total_loss ?? '-' }}</td>
                            <td>{{ $test->technician ?? '-' }}</td>
                            <td>
                                <a href="{{ route('fiber-test-results.show', $test) }}" class="btn btn-sm btn-primary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                No data found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="card-footer">
            {{ $testResults->links() }}
        </div>
    </div>
</div>
@endsection
