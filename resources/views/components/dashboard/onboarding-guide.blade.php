@props(['guide', 'requiresCompletion' => false])

<section
    class="dashboard-onboarding"
    data-onboarding-guide
    data-requires-completion="{{ $requiresCompletion ? 'true' : 'false' }}"
    data-complete-url="{{ route('onboarding.complete') }}"
    hidden
>
    <div class="dashboard-onboarding-dialog" role="dialog" aria-modal="true" aria-labelledby="onboarding-title" tabindex="-1">
        <header>
            <span class="dashboard-onboarding-icon"><x-dashboard.icon name="clipboard" size="24" /></span>
            <div>
                <span>Getting Started</span>
                <h2 id="onboarding-title">{{ $guide['title'] }}</h2>
            </div>
            <button type="button" aria-label="Close guide" data-guide-close><x-dashboard.icon name="x" size="20" /></button>
        </header>

        <p>{{ $guide['introduction'] }}</p>
        <ol>
            @foreach ($guide['steps'] as $step)
                <li><span>{{ $loop->iteration }}</span><div><strong>{{ $step['title'] }}</strong><p>{{ $step['description'] }}</p></div></li>
            @endforeach
        </ol>
        <p class="dashboard-onboarding-support">{{ $guide['support'] }}</p>
        <footer><button class="dashboard-primary-action" type="button" data-guide-finish>Finish Guide</button></footer>
    </div>
</section>
