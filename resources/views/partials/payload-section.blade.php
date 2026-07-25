<section class="payload">
    <div class="payload-head">
        <h2>{{ $title }}</h2>
        <button
            type="button"
            class="copy-btn"
            data-copy-target="{{ $id }}"
            aria-label="Copy {{ $title }}"
        >
            <span class="copy-btn-label">Copy</span>
        </button>
    </div>
    <pre id="{{ $id }}">{{ $content }}</pre>
</section>
