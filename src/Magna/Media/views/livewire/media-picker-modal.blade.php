{{--
  Global Magna media-picker modal.

  Include once per page/component:   <livewire:magna-media-picker />
  Open from PHP:                     $this->dispatch('magna:open-media-picker', target: 'my-field')
  Open from Alpine/blade:            @click="$dispatch('magna:open-media-picker', { target: 'my-field' })"
  Listen for result:                 #[On('magna:media-selected')] onMediaSelected(path, url, disk, target)
--}}

@assets
<style>
/* ── Magna Media Picker — scoped design tokens ────────────────────────── */
.mmp-modal {
    --mmp-bg:     #ffffff;
    --mmp-border: #e2e8f0;
    --mmp-text:   #0f172a;
    --mmp-muted:  #64748b;
    --mmp-ph:     #94a3b8;
    --mmp-brand:  #6366f1;
    --mmp-brand-h:#4f46e5;
}
html.dark .mmp-modal {
    --mmp-bg:     #0f172a;
    --mmp-border: #1e293b;
    --mmp-text:   #f1f5f9;
    --mmp-muted:  #94a3b8;
    --mmp-ph:     #475569;
    --mmp-brand:  #6366f1;
    --mmp-brand-h:#818cf8;
}
/* ── Backdrop ──────────────────────────────────────────────────────────── */
.mmp-backdrop {
    position: fixed; inset: 0; z-index: 600;
    background: rgba(0,0,0,.65); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center; padding: 1.5rem;
}
/* ── Modal shell ───────────────────────────────────────────────────────── */
.mmp-modal {
    background: var(--mmp-bg); border: 1px solid var(--mmp-border);
    border-radius: 12px; width: 100%; max-width: 720px; max-height: 82vh;
    display: flex; flex-direction: column;
    box-shadow: 0 24px 64px rgba(0,0,0,.4); overflow: hidden;
    color: var(--mmp-text); font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
    -webkit-font-smoothing: antialiased;
}
/* ── Header ────────────────────────────────────────────────────────────── */
.mmp-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--mmp-border); flex-shrink: 0;
}
.mmp-title { font-size: .9rem; font-weight: 600; }
.mmp-close {
    width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
    border: 0; background: transparent; cursor: pointer; border-radius: 6px;
    color: var(--mmp-muted); transition: background .15s;
}
.mmp-close:hover { background: var(--mmp-border); color: var(--mmp-text); }
.mmp-close svg { width: 16px; height: 16px; }
/* ── Toolbar ───────────────────────────────────────────────────────────── */
.mmp-toolbar {
    display: flex; align-items: stretch; gap: .6rem;
    padding: .8rem 1.25rem; border-bottom: 1px solid var(--mmp-border); flex-shrink: 0;
}
.mmp-upload {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: .5rem;
    border: 1.5px dashed var(--mmp-border); border-radius: 9px; padding: .55rem .9rem;
    font-size: .8rem; color: var(--mmp-muted); cursor: pointer; text-align: center;
    transition: border-color .15s, color .15s, background .15s;
}
.mmp-upload:hover { border-color: var(--mmp-brand); color: var(--mmp-text); background: rgba(99,102,241,.05); }
.mmp-upload svg { width: 17px; height: 17px; flex-shrink: 0; }
.mmp-upload-input { display: none; }
.mmp-upload-link { color: var(--mmp-brand); font-weight: 600; }
.mmp-search { position: relative; width: 190px; flex-shrink: 0; }
.mmp-search svg {
    position: absolute; left: .6rem; top: 50%; transform: translateY(-50%);
    width: 15px; height: 15px; color: var(--mmp-muted); pointer-events: none;
}
.mmp-search-input {
    width: 100%; height: 100%; padding: .5rem .75rem .5rem 2rem; border-radius: 8px;
    border: 1px solid var(--mmp-border); background: var(--mmp-bg); color: var(--mmp-text);
    font-size: .82rem; outline: none; transition: border-color .15s;
}
.mmp-search-input:focus { border-color: var(--mmp-brand); }
.mmp-search-input::placeholder { color: var(--mmp-ph); }
/* ── Body / grid ───────────────────────────────────────────────────────── */
.mmp-body { flex: 1; overflow-y: auto; padding: 1rem; }
.mmp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px,1fr)); gap: .75rem; }
.mmp-item {
    aspect-ratio: 4/3; border-radius: 8px; overflow: hidden;
    cursor: pointer; border: 2px solid transparent;
    transition: border-color .15s, transform .15s, box-shadow .15s;
    background: var(--mmp-border); position: relative; padding: 0;
}
.mmp-item:hover { border-color: var(--mmp-brand); transform: scale(1.02); }
.mmp-item.is-picked { border-color: var(--mmp-brand); box-shadow: 0 0 0 2px var(--mmp-brand); }
.mmp-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
.mmp-item-name {
    position: absolute; bottom: 0; left: 0; right: 0; padding: .3rem .45rem;
    background: rgba(0,0,0,.6); color: #fff;
    font-size: .65rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.mmp-check {
    position: absolute; top: .35rem; right: .35rem; width: 22px; height: 22px;
    border-radius: 50%; background: var(--mmp-brand); color: #fff;
    display: flex; align-items: center; justify-content: center;
}
.mmp-check svg { width: 13px; height: 13px; }
.mmp-empty { text-align: center; padding: 3rem 1rem; color: var(--mmp-muted); font-size: .875rem; }
.mmp-empty svg { width: 44px; height: 44px; margin: 0 auto .75rem; opacity: .3; display: block; }
/* ── Footer ────────────────────────────────────────────────────────────── */
.mmp-footer {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    padding: .8rem 1.25rem; border-top: 1px solid var(--mmp-border); flex-shrink: 0;
}
.mmp-hint { font-size: .78rem; color: var(--mmp-muted); }
.mmp-actions { display: flex; gap: .5rem; }
.mmp-btn {
    padding: .45rem 1rem; border-radius: 7px; font-size: .82rem; font-weight: 500;
    cursor: pointer; border: 1px solid var(--mmp-border); transition: background .15s, border-color .15s;
    background: transparent; color: var(--mmp-text);
}
.mmp-btn:hover { background: var(--mmp-border); }
.mmp-btn-primary {
    background: var(--mmp-brand); border-color: var(--mmp-brand); color: #fff;
}
.mmp-btn-primary:hover:not(:disabled) { background: var(--mmp-brand-h); border-color: var(--mmp-brand-h); }
.mmp-btn-primary:disabled { opacity: .45; cursor: not-allowed; }
</style>
@endassets

<div>
@if($open)
{{-- Backdrop (click outside closes) --}}
<div class="mmp-backdrop"
     role="dialog" aria-modal="true" aria-label="Media library"
     wire:click.self="close"
     x-data="{
         q: '',
         picked: null,
         pickedDisk: null,
         hit(name) { return this.q === '' || name.toLowerCase().includes(this.q.toLowerCase().trim()); }
     }"
     @keydown.escape.window="$wire.close()"
>
    <div class="mmp-modal">

        {{-- Header --}}
        <div class="mmp-header">
            <span class="mmp-title">Media library</span>
            <button type="button" class="mmp-close" wire:click="close" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        {{-- Toolbar: upload + search --}}
        <div class="mmp-toolbar">
            <label class="mmp-upload">
                <input type="file" accept="{{ $mimeFilter }}" wire:model="deviceUpload" class="mmp-upload-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/>
                    <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                <span wire:loading.remove wire:target="deviceUpload">
                    Drag a file here or <span class="mmp-upload-link">choose from your device</span>
                </span>
                <span wire:loading wire:target="deviceUpload">Uploading…</span>
            </label>

            @if (count($files) > 0)
            <div class="mmp-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" x-model="q" class="mmp-search-input"
                       placeholder="Search…" aria-label="Search"/>
            </div>
            @endif
        </div>

        {{-- Grid --}}
        <div class="mmp-body">
            @if (count($files) > 0)
                <div class="mmp-grid">
                    @foreach ($files as $file)
                        <button type="button"
                                class="mmp-item"
                                :class="{ 'is-picked': picked === @js($file['path']) }"
                                x-show="hit(@js($file['name']))"
                                @click="picked = @js($file['path']); pickedDisk = @js($file['disk'])"
                                x-on:dblclick="$wire.confirm(@js($file['path']), @js($file['disk']))"
                                title="{{ $file['name'] }} — click to select, double-click to use">
                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" loading="lazy"/>
                            <span class="mmp-item-name">{{ $file['name'] }}</span>
                            <span class="mmp-check" x-show="picked === @js($file['path'])" x-cloak aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </span>
                        </button>
                    @endforeach
                </div>
            @else
                <div class="mmp-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                    <p>No files in the media library yet.<br>
                       Use the upload option above, or add files via <strong>Media</strong> in the admin sidebar.</p>
                </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="mmp-footer">
            <span class="mmp-hint" x-text="picked ? '1 file selected' : 'Click a file to select'"></span>
            <div class="mmp-actions">
                <button type="button" class="mmp-btn" wire:click="close">Cancel</button>
                <button type="button" class="mmp-btn mmp-btn-primary"
                        :disabled="!picked"
                        x-on:click="$wire.confirm(picked, pickedDisk)">
                    Choose file
                </button>
            </div>
        </div>

    </div>
</div>

@endif
</div>
