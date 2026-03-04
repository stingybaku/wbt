@php
    /** @var ?string $routeKey */
    /** @var ?string $textKey */
    /** @var array<string, array<string, int>> $routeHotspots */
    /** @var ?array<string, int> $selectedHotspot */
    /** @var bool $missingHotspot */
@endphp

<div style="display:grid; gap:12px;">
    <div style="position:relative; width:min(680px, 100%); aspect-ratio: 16 / 9; border:1px solid #d1d5db; border-radius:12px; overflow:hidden; background:#f3f4f6;">
        @if ($routeKey === 'auth.login')
            <div style="position:absolute; inset:0; background:#ececee;"></div>
            <div style="position:absolute; top:-60px; left:-40px; width:180px; height:180px; border-radius:999px; background:radial-gradient(circle at 30% 30%, #ff5d52 0%, #ff2c26 60%, #d10e0e 100%);"></div>
            <div style="position:absolute; right:-80px; bottom:-90px; width:180px; height:180px; border-radius:999px; background:radial-gradient(circle at 30% 30%, #ff5d52 0%, #ff2c26 60%, #d10e0e 100%);"></div>
            <div style="position:absolute; top:8%; left:5%; width:90%; height:84%; border-radius:10px; background:#f8f8f8; box-shadow:0 14px 28px rgba(15,23,42,.16); display:grid; grid-template-columns:1fr 1fr;">
                <div style="padding:26px 22px; border-right:1px solid #e5e7eb;">
                    <div style="height:10px; width:90px; background:#d1d5db; border-radius:4px; margin:0 auto 12px;"></div>
                    <div style="height:16px; width:140px; background:#f97316; border-radius:4px; margin:0 auto 16px;"></div>
                    <div style="height:8px; width:180px; background:#d1d5db; border-radius:4px; margin:0 auto 22px;"></div>
                    <div style="height:18px; width:220px; background:#e5e7eb; border-radius:999px; margin:0 auto 10px;"></div>
                    <div style="height:18px; width:220px; background:#e5e7eb; border-radius:999px; margin:0 auto 10px;"></div>
                    <div style="height:20px; width:220px; background:linear-gradient(90deg,#ff5d34,#f10b1b); border-radius:999px; margin:0 auto 12px;"></div>
                    <div style="height:8px; width:170px; background:#e5e7eb; border-radius:4px; margin:0 auto 10px;"></div>
                    <div style="height:8px; width:20px; background:#e5e7eb; border-radius:4px; margin:0 auto 10px;"></div>
                    <div style="height:8px; width:150px; background:#e5e7eb; border-radius:4px; margin:0 auto;"></div>
                </div>
                <div style="position:relative; background:linear-gradient(140deg, rgba(244,63,35,.80), rgba(220,38,38,.92));">
                    <div style="position:absolute; inset:0; background:repeating-linear-gradient(100deg, rgba(255,255,255,.08) 0, rgba(255,255,255,.08) 1px, transparent 1px, transparent 40px);"></div>
                    <div style="position:absolute; left:15%; right:15%; bottom:23%;">
                        <div style="height:18px; width:120px; background:rgba(255,255,255,.9); border-radius:4px; margin:0 auto 12px;"></div>
                        <div style="height:26px; width:190px; background:rgba(255,255,255,.95); border-radius:4px; margin:0 auto 12px;"></div>
                        <div style="height:8px; width:220px; background:rgba(255,255,255,.72); border-radius:4px; margin:0 auto;"></div>
                    </div>
                </div>
            </div>
        @elseif ($routeKey === 'projects.list')
            <div style="position:absolute; inset:0; background:linear-gradient(140deg,#fde68a 0%, #f8fafc 45%, #e2e8f0 100%);"></div>
            <div style="position:absolute; top:10%; left:6%; right:6%; bottom:10%; display:grid; gap:10px;">
                <div style="background:rgba(255,255,255,.9); border:1px solid #d1d5db; border-radius:10px; padding:10px; display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="width:55%;">
                        <div style="height:12px; width:120px; background:#d1d5db; border-radius:4px; margin-bottom:8px;"></div>
                        <div style="height:8px; width:180px; background:#e5e7eb; border-radius:4px;"></div>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <div style="height:22px; width:72px; background:#e5e7eb; border-radius:8px;"></div>
                        <div style="height:22px; width:60px; background:#e5e7eb; border-radius:8px;"></div>
                    </div>
                </div>
                <div style="background:rgba(255,255,255,.9); border:1px solid #d1d5db; border-radius:10px; padding:10px;">
                    <div style="height:10px; width:130px; background:#d1d5db; border-radius:4px; margin-bottom:8px;"></div>
                    <div style="height:8px; width:90px; background:#e5e7eb; border-radius:4px; margin-bottom:6px;"></div>
                    <div style="height:24px; width:220px; background:#f8fafc; border:1px solid #d1d5db; border-radius:8px; margin-bottom:8px;"></div>
                    <div style="height:22px; width:110px; background:#111827; border-radius:8px;"></div>
                </div>
                <div style="background:rgba(255,255,255,.9); border:1px solid #d1d5db; border-radius:10px; padding:10px;">
                    <div style="height:10px; width:130px; background:#d1d5db; border-radius:4px; margin-bottom:8px;"></div>
                    <div style="height:8px; width:110px; background:#e5e7eb; border-radius:4px; margin-bottom:8px;"></div>
                    <div style="height:26px; border:1px solid #e5e7eb; border-radius:8px; background:#fff;"></div>
                </div>
            </div>
        @else
            <div style="position:absolute; inset:0; display:grid; place-items:center; color:#6b7280; font-size:14px;">
                Select a route key to preview its page
            </div>
        @endif

        @foreach ($routeHotspots as $previewKey => $previewHotspot)
            <div
                title="{{ $previewKey }}"
                style="
                    position:absolute;
                    top: {{ $previewHotspot['top'] }}%;
                    left: {{ $previewHotspot['left'] }}%;
                    width: {{ $previewHotspot['width'] }}%;
                    height: {{ $previewHotspot['height'] }}%;
                    border:1px dashed rgba(30, 41, 59, .28);
                    border-radius:7px;
                    background:rgba(255, 255, 255, .18);
                    pointer-events:none;
                "
            ></div>
        @endforeach

        @if ($selectedHotspot)
            <div
                style="
                    position:absolute;
                    top: {{ $selectedHotspot['top'] }}%;
                    left: {{ $selectedHotspot['left'] }}%;
                    width: {{ $selectedHotspot['width'] }}%;
                    height: {{ $selectedHotspot['height'] }}%;
                    border:2px solid #2563eb;
                    border-radius:8px;
                    box-shadow:0 0 0 2px rgba(255, 255, 255, .95), 0 0 0 9999px rgba(37, 99, 235, .12);
                    pointer-events:none;
                "
            ></div>
            <div
                style="
                    position:absolute;
                    top: calc({{ $selectedHotspot['top'] }}% - 26px);
                    left: {{ $selectedHotspot['left'] }}%;
                    max-width: 58%;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    font-size: 11px;
                    line-height: 1;
                    padding: 6px 8px;
                    border-radius: 999px;
                    border:1px solid #1d4ed8;
                    background:#eff6ff;
                    color:#1e40af;
                    pointer-events:none;
                "
            >
                {{ $textKey }}
            </div>
        @endif
    </div>

    <div style="display:grid; gap:8px;">
        <div style="color:#111827; font-size:13px;">
            @if ($textKey)
                <strong>Selected key:</strong> {{ $textKey }}
            @else
                <strong>No text key selected.</strong> Pick one to highlight it in the preview.
            @endif
        </div>
        @if ($missingHotspot)
            <div style="color:#92400e; background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:8px 10px; font-size:12px;">
                This key is valid, but no preview hotspot is mapped yet.
            </div>
        @endif
        @if (!empty($routeHotspots))
            <div style="color:#6b7280; font-size:12px;">
                Mapped regions: {{ count($routeHotspots) }}
            </div>
        @endif
        @if (!empty($routeHotspots))
            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                @foreach (array_keys($routeHotspots) as $previewKey)
                    <span
                        style="
                            font-size:11px;
                            padding:4px 8px;
                            border-radius:999px;
                            border:1px solid {{ $previewKey === $textKey ? '#2563eb' : '#d1d5db' }};
                            background: {{ $previewKey === $textKey ? 'rgba(37, 99, 235, .12)' : '#ffffff' }};
                            color:#111827;
                        "
                    >
                        {{ $previewKey }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</div>
