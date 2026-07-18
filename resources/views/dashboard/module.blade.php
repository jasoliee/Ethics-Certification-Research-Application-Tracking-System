@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page dashboard-placeholder-page">
        <section class="dashboard-placeholder">
            <span class="dashboard-placeholder-icon"><x-dashboard.icon :name="$moduleIcon" size="42" /></span>
            <h1>{{ $moduleTitle }}</h1>
            <p>{{ $moduleMessage }}</p>
        </section>
    </div>
@endsection
