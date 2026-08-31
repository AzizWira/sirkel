@php
    $serverThemePreference = auth()->check() ? (auth()->user()->theme_preference ?? 'system') : null;
    $themeUserId = auth()->id();
@endphp
<script>
    (function () {
        var serverPreference = @json($serverThemePreference);
        var userId = @json($themeUserId);
        var userKey = userId ? 'sirkel-theme-user-' + userId : null;
        var preference = null;

        try {
            if (userKey) preference = localStorage.getItem(userKey);
            if (!preference && !userKey) preference = localStorage.getItem('sirkel-theme');
        } catch (e) { }

        if (!['light', 'dark', 'system'].includes(preference)) preference = serverPreference;
        if (!['light', 'dark', 'system'].includes(preference)) preference = 'system';

        var effective = preference === 'system'
            ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : preference;

        document.documentElement.dataset.userTheme = preference;
        document.documentElement.dataset.theme = effective;

        try {
            localStorage.setItem('sirkel-theme', preference);
            if (userKey) localStorage.setItem(userKey, preference);
        } catch (e) { }
    })();
</script>