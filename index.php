<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VibeMap - Share Your Moments</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="icon" type="logo" href="logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #f5f0e8;
      --bg-warm: #fff8f0;
      --card: #ffffff;
      --border: #e8dfd4;
      --fg: #2d2a26;
      --muted: #8a8279;
      --accent: #ff6b4a;
      --accent-soft: #fff0ec;
      --mint: #4ecdc4;
      --mint-soft: #e8faf8;
      --lavender: #a78bfa;
      --lavender-soft: #f3f0ff;
      --gold: #fbbf24;
      --gold-soft: #fffbeb;
      --blue: #3b82f6;
      --blue-soft: #eff6ff;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Outfit', sans-serif;
      background: var(--bg);
      color: var(--fg);
      min-height: 100vh;
      overflow: hidden;
    }

    .mono {
      font-family: 'Space Mono', monospace;
    }

    ::-webkit-scrollbar {
      width: 5px;
    }
    ::-webkit-scrollbar-track {
      background: transparent;
    }
    ::-webkit-scrollbar-thumb {
      background: var(--border);
      border-radius: 10px;
    }

    /* Leaflet customization */
    .leaflet-container {
      background: linear-gradient(160deg, #edf4ff 0%, #f8f2e9 44%, #eef8f0 100%) !important;
    }

    .leaflet-tile-pane {
      filter: saturate(1.14) contrast(1.06) brightness(0.98) hue-rotate(-8deg);
    }

    .leaflet-control-zoom {
      border: none !important;
      box-shadow: 0 14px 30px rgba(15, 23, 42, 0.18) !important;
      border-radius: 12px !important;
      overflow: hidden;
    }

    .leaflet-control-zoom a {
      background: rgba(255, 255, 255, 0.92) !important;
      color: #334155 !important;
      border: none !important;
      width: 36px !important;
      height: 36px !important;
      line-height: 36px !important;
      font-size: 16px !important;
    }

    .leaflet-control-zoom a:hover {
      background: #ffffff !important;
      color: #1d4ed8 !important;
    }

    .leaflet-control-attribution {
      background: rgba(255, 255, 255, 0.75) !important;
      color: #5b6678 !important;
      font-size: 9px !important;
      backdrop-filter: blur(8px);
    }

    .leaflet-popup-content-wrapper {
      background: var(--card) !important;
      border-radius: 16px !important;
      box-shadow: 0 10px 40px rgba(0,0,0,0.12) !important;
      overflow: hidden;
    }

    .leaflet-popup-content {
      margin: 0 !important;
      color: var(--fg) !important;
      font-family: 'Outfit', sans-serif !important;
    }

    .leaflet-popup-tip {
      background: var(--card) !important;
    }

    .leaflet-popup-close-button {
      color: var(--muted) !important;
      font-size: 20px !important;
      top: 8px !important;
      right: 10px !important;
    }

    .map-stage {
      position: relative;
      overflow: hidden;
      isolation: isolate;
    }

    .map-vignette {
      position: absolute;
      inset: 0;
      pointer-events: none;
      z-index: 350;
      background:
        radial-gradient(circle at 12% 8%, rgba(251, 191, 36, 0.24), rgba(251, 191, 36, 0) 38%),
        radial-gradient(circle at 88% 18%, rgba(56, 189, 248, 0.22), rgba(56, 189, 248, 0) 42%),
        radial-gradient(circle at 50% 58%, rgba(255, 255, 255, 0) 46%, rgba(15, 23, 42, 0.25) 100%);
    }

    .map-aurora {
      position: absolute;
      inset: -10% -8%;
      pointer-events: none;
      z-index: 355;
      opacity: 0.42;
      background:
        conic-gradient(from 210deg at 70% 30%, rgba(244, 114, 182, 0.28), rgba(251, 191, 36, 0.08), rgba(56, 189, 248, 0.32), rgba(244, 114, 182, 0.28));
      filter: blur(44px);
      mix-blend-mode: soft-light;
    }

    .map-grain {
      position: absolute;
      inset: 0;
      pointer-events: none;
      z-index: 360;
      opacity: 0.14;
      background-image:
        radial-gradient(rgba(28, 44, 66, 0.14) 0.7px, transparent 0.7px),
        radial-gradient(rgba(255, 255, 255, 0.18) 0.7px, transparent 0.7px);
      background-size: 3px 3px, 5px 5px;
      background-position: 0 0, 1px 2px;
      mix-blend-mode: soft-light;
    }

    .popup-post-card {
      width: 260px;
      max-width: min(260px, 100%);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      gap: 10px;
      padding: 6px 10px 2px;
    }

    .popup-post-header {
      display: flex;
      align-items: center;
      gap: 11px;
    }

    .popup-post-avatar {
      margin-bottom: 35px;
      width: 40px;
      height: 40px;
      border-radius: 9999px;
      overflow: hidden;
      border: 2px solid #ffffff;
      background: #ffffff;
      box-shadow: 0 2px 9px rgba(15, 23, 42, 0.14);
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .popup-post-avatar-fallback {
      background: linear-gradient(135deg, var(--mint), var(--lavender));
      color: #fff;
      font-size: 13px;
      font-weight: 700;
    }

    .popup-post-meta {
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    .popup-post-username {
      margin: 0;
      display: block;
      font-size: 16px;
      font-weight: 700;
      line-height: 1.1;
      color: #2b2730;
    }

    .popup-post-time {
      margin: 0;
      font-size: 12px;
      color: #8a8279;
      line-height: 1.2;
    }

    .popup-post-content {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .popup-post-note {
      margin: 0;
      font-size: 15px;
      line-height: 1.35;
      white-space: pre-wrap;
      word-break: break-word;
      color: #2f2a26;
    }

    .popup-post-empty {
      margin: 0;
      font-size: 13px;
      color: var(--muted);
    }

    .popup-post-photo {
      display: block;
      width: 100%;
      max-width: 100%;
      max-height: 192px;
      object-fit: cover;
    }

    .popup-post-actions {
      margin-top: 10px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }

    .popup-views-chip {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      min-height: 36px;
      padding: 0 12px;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: rgba(255, 255, 255, 0.88);
      color: #64748b;
      font-size: 12px;
      font-weight: 600;
      line-height: 1;
      box-shadow: 0 1px 6px rgba(15, 23, 42, 0.08);
    }

    .popup-views-icon {
      color: #60728c;
      flex-shrink: 0;
    }

    .popup-views-value {
      color: #485a73;
      font-size: 14px;
      font-weight: 700;
      line-height: 1;
    }

    .popup-delete-btn {
      width: 38px;
      height: 38px;
      border-radius: 12px;
      border: 1px solid #f1d7c5;
      background: linear-gradient(180deg, #fffdfa 0%, #fff4ec 100%);
      color: #c25a18;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 3px 10px rgba(194, 90, 24, 0.12);
      transition: color 0.2s ease, border-color 0.2s ease, background-color 0.2s ease, transform 0.15s ease;
    }

    .popup-delete-btn svg {
      width: 17px;
      height: 17px;
    }

    .popup-delete-btn:hover {
      color: #dc2626;
      border-color: #fecaca;
      background: #fff5f5;
      transform: translateY(-1px);
    }

    /* User location marker */
    .user-location-marker {
      position: relative;
    }

    .user-dot {
      width: 20px;
      height: 20px;
      background: var(--blue);
      border: 3px solid white;
      border-radius: 50%;
      box-shadow: 0 2px 10px rgba(59, 130, 246, 0.5);
    }

    .user-pulse {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 40px;
      height: 40px;
      background: var(--blue);
      border-radius: 50%;
      opacity: 0;
      animation: userPulse 2s ease-out infinite;
    }

    @keyframes userPulse {
      0% { transform: translate(-50%, -50%) scale(0.5); opacity: 0.5; }
      100% { transform: translate(-50%, -50%) scale(2); opacity: 0; }
    }

    /* Custom marker */
    .vibe-marker {
      position: relative;
      cursor: pointer;
    }

    .marker-outer {
      width: 44px;
      height: 44px;
      border-radius: 50% 50% 50% 4px;
      transform: rotate(-45deg);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      border: 3px solid white;
    }

    .vibe-marker:hover .marker-outer {
      transform: rotate(-45deg) scale(1.1);
      box-shadow: 0 8px 25px rgba(0,0,0,0.25);
    }

    .marker-inner {
      transform: rotate(45deg);
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: white;
    }

    .marker-pulse {
      position: absolute;
      bottom: -8px;
      left: 50%;
      transform: translateX(-50%) rotate(-45deg);
      width: 30px;
      height: 30px;
      border-radius: 50%;
      opacity: 0;
      animation: markerPulse 2s ease-out infinite;
    }

    .marker-pulse-circle {
      bottom: 50%;
      transform: translateX(-50%);
      animation: markerPulseCircle 2s ease-out infinite;
    }

    @keyframes markerPulse {
      0% { transform: translateX(-50%) rotate(-45deg) scale(0.5); opacity: 0.6; }
      100% { transform: translateX(-50%) rotate(-45deg) scale(2.5); opacity: 0; }
    }

    @keyframes markerPulseCircle {
      0% { transform: translateX(-50%) scale(0.5); opacity: 0.6; }
      100% { transform: translateX(-50%) scale(2.2); opacity: 0; }
    }

    .marker-avatar-wrap {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      overflow: hidden;
      border: 3px solid white;
      background: var(--card);
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .marker-avatar {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .vibe-marker:hover .marker-avatar-wrap {
      transform: scale(1.08);
      box-shadow: 0 8px 25px rgba(0,0,0,0.25);
    }

    /* Animations */
    @keyframes slideInLeft {
      from { opacity: 0; transform: translateX(-20px); }
      to { opacity: 1; transform: translateX(0); }
    }

    @keyframes slideInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes scaleIn {
      from { opacity: 0; transform: scale(0.9); }
      to { opacity: 1; transform: scale(1); }
    }

    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-5px); }
    }

    @keyframes locating {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }

    .animate-slide-in { animation: slideInLeft 0.4s ease-out forwards; }
    .animate-slide-up { animation: slideInUp 0.4s ease-out forwards; }
    .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
    .animate-scale-in { animation: scaleIn 0.3s ease-out forwards; }

    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
      }
    }

    *:focus-visible {
      outline: 2px solid var(--accent);
      outline-offset: 2px;
    }

    /* Photo preview */
    .photo-preview {
      position: relative;
      overflow: hidden;
      border-radius: 12px;
    }

    .photo-preview img {
      transition: transform 0.3s ease;
    }

    .photo-preview:hover img {
      transform: scale(1.05);
    }

    /* Music card */
    .music-card {
      background: linear-gradient(135deg, var(--lavender-soft), var(--mint-soft));
      border-radius: 12px;
      padding: 12px;
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      overflow: hidden;
    }

    .music-art {
      width: 48px;
      height: 48px;
      border-radius: 8px;
      background: linear-gradient(135deg, var(--lavender), var(--mint));
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .music-preview-player {
      flex: 1 1 100%;
      width: 100%;
      max-width: 100%;
      min-width: 0;
      display: block;
    }

    /* Story-style composer */
    .story-composer-shell {
      border: 1px solid var(--border);
      border-radius: 20px;
      background: var(--bg);
      padding: 12px;
    }

    .story-meta {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
    }

    .story-avatar {
      width: 34px;
      height: 34px;
      border-radius: 9999px;
      overflow: hidden;
      border: 2px solid white;
      background: linear-gradient(135deg, var(--mint), var(--lavender));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 13px;
      font-weight: 700;
      flex-shrink: 0;
    }

    .story-username {
      font-size: 15px;
      font-weight: 700;
      line-height: 1.1;
    }

    .story-song {
      font-size: 12px;
      color: var(--muted);
      white-space: nowrap;
      text-overflow: ellipsis;
      overflow: hidden;
      max-width: 100%;
    }

    .story-chip-row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 10px;
    }

    .story-chip {
      border: 1px solid #d6e9ff;
      background: #eef6ff;
      color: #2263a9;
      border-radius: 9px;
      padding: 6px 10px;
      font-size: 12px;
      line-height: 1;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .story-chip-dark {
      border: 1px solid rgba(255, 255, 255, 0.22);
      background: rgba(13, 22, 43, 0.35);
      color: white;
      border-radius: 9px;
      padding: 7px 10px;
      font-size: 12px;
      line-height: 1;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .story-actions {
      display: flex;
      gap: 8px;
      margin-bottom: 10px;
    }

    .story-canvas {
      position: relative;
      border-radius: 16px;
      min-height: 300px;
      padding: 20px 18px 34px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .story-note-input {
      width: 100%;
      min-height: 220px;
      max-height: 260px;
      border: 0;
      outline: none;
      background: transparent;
      color: #fff;
      text-align: center;
      font-weight: 700;
      font-size: clamp(28px, 5.2vw, 48px);
      line-height: 1.08;
      resize: none;
      text-shadow: 0 2px 14px rgba(0, 0, 0, 0.26);
    }

    .story-note-input::placeholder {
      color: rgba(255, 255, 255, 0.88);
    }

    .story-char-count {
      position: absolute;
      right: 12px;
      bottom: 10px;
      color: rgba(255, 255, 255, 0.8);
      font-size: 11px;
      font-family: 'Space Mono', monospace;
      letter-spacing: 0.01em;
    }

    .story-bg-theme-0 {
      background: linear-gradient(165deg, #d600ff 0%, #8d00d4 50%, #1f2f9d 100%);
    }

    .story-bg-theme-1 {
      background: linear-gradient(160deg, #ff2d95 0%, #b70dff 45%, #3556ff 100%);
    }

    .story-bg-theme-2 {
      background: linear-gradient(160deg, #ff6f61 0%, #ff3e9d 50%, #6a40ff 100%);
    }

    .story-bg-theme-3 {
      background: linear-gradient(165deg, #0099ff 0%, #0056d1 45%, #2b1364 100%);
    }

    .story-bg-theme-4 {
      background: linear-gradient(170deg, #4f16c5 0%, #9b00ff 45%, #ff13c0 100%);
    }

    /* Blob decorations */
    .blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(60px);
      opacity: 0.4;
      pointer-events: none;
    }

    /* Locate button */
    .locate-btn {
      transition: all 0.2s ease;
    }

    .locate-btn.locating {
      animation: locating 1s ease-in-out infinite;
    }
  </style>
</head>
<body>
  <!-- Decorative blobs -->
  <div class="blob w-96 h-96 bg-[var(--accent)] opacity-20 -top-48 -right-48 fixed"></div>
  <div class="blob w-80 h-80 bg-[var(--mint)] opacity-20 bottom-0 left-0 fixed"></div>

  <!-- Main Container -->
  <div class="flex h-screen relative">
    <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-black/35 backdrop-blur-sm md:hidden"></div>
    <button id="sidebar-toggle-btn" type="button" class="fixed top-4 left-4 z-30 md:hidden w-11 h-11 rounded-xl bg-[var(--card)] border border-[var(--border)] shadow-lg flex items-center justify-center hover:border-[var(--accent)] transition-colors" aria-label="Open sidebar">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>

    <aside id="sidebar" class="fixed inset-y-0 left-0 w-full max-w-full bg-[var(--card)] border-r border-[var(--border)] z-40 flex flex-col transform -translate-x-full transition-transform duration-200 ease-out md:relative md:inset-auto md:z-20 md:translate-x-0 md:w-72 md:max-w-none md:flex-shrink-0">
      <div class="p-5 flex items-center gap-3">
        <div id="sidebar-avatar" class="w-12 h-12 rounded-full overflow-hidden bg-gradient-to-br from-[var(--mint)] to-[var(--lavender)] flex items-center justify-center text-white font-semibold">
          U
        </div>
          <div class="min-w-0">
            <p class="text-xs text-[var(--muted)]">Signed in as</p>
            <p id="sidebar-full-name" class="text-sm font-semibold truncate">User</p>
            <p id="sidebar-username" class="text-xs text-[var(--muted)] truncate"></p>
          </div>
        </div>
      <div class="px-5 pb-5 mt-auto space-y-2">
        <button id="sidebar-profile-btn" type="button" class="w-full py-2.5 rounded-xl border border-[var(--border)] bg-[var(--bg)] text-sm font-semibold hover:border-[var(--mint)] hover:text-[var(--mint)] transition-colors">
          Edit Profile
        </button>
        <button id="sidebar-logout-btn" type="button" class="w-full py-2.5 rounded-xl border border-[var(--border)] bg-[var(--bg)] text-sm font-semibold hover:border-[var(--accent)] hover:text-[var(--accent)] transition-colors">
          Logout
        </button>
      </div>
    </aside>

    <!-- Map Container -->
    <div class="flex-1 relative z-10 map-stage">
      <div id="map" class="absolute inset-0"></div>
      <div class="map-vignette"></div>
      <div class="map-aurora"></div>
      <div class="map-grain"></div>
    </div>

    <!-- Drop Vibe Floating Button -->
    <button
      id="add-post-btn"
      class="fixed bottom-36 md:bottom-24 right-4 md:right-6 z-20 py-3 px-4 rounded-2xl bg-gradient-to-r from-[var(--accent)] to-[#ff8a65] text-white font-semibold flex items-center justify-center gap-2 shadow-xl shadow-[var(--accent)]/30 hover:shadow-2xl active:scale-[0.98] transition-all"
      aria-label="Drop a vibe at your location"
    >
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
    </button>

    <!-- Locate Me Button -->
    <button 
      id="locate-btn"
      class="locate-btn fixed bottom-20 md:bottom-6 right-4 md:right-6 z-20 w-14 h-14 rounded-2xl bg-[var(--card)] border border-[var(--border)] flex items-center justify-center shadow-xl hover:shadow-2xl hover:border-[var(--blue)] transition-all"
      aria-label="Find my location"
      title="Find my location"
    >
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2" stroke-linecap="round">
        <circle cx="12" cy="12" r="4"></circle>
        <line x1="12" y1="2" x2="12" y2="6"></line>
        <line x1="12" y1="18" x2="12" y2="22"></line>
        <line x1="2" y1="12" x2="6" y2="12"></line>
        <line x1="18" y1="12" x2="22" y2="12"></line>
      </svg>
    </button>

    <!-- Create Post Modal -->
    <div id="post-modal" class="fixed inset-0 z-50 hidden">
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm animate-fade-in" id="modal-backdrop"></div>
      <div class="absolute inset-0 flex items-start sm:items-center justify-center p-0 sm:p-4 overflow-y-auto">
        <div class="my-0 bg-[var(--card)] rounded-none sm:rounded-3xl w-full sm:max-w-lg h-full sm:h-auto shadow-2xl animate-scale-in overflow-hidden sm:max-h-[calc(100dvh-2rem)] flex flex-col">
          <!-- Modal Header -->
          <div class="p-5 border-b border-[var(--border)] bg-gradient-to-b from-[var(--bg-warm)] to-[var(--card)] shrink-0">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div id="post-modal-avatar" class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-[var(--mint)] to-[var(--lavender)] flex items-center justify-center text-white font-semibold">
                  U
                </div>
                <div>
                  <p class="font-semibold">Create New Vibe</p>
                  <p class="text-xs text-[var(--muted)] mono" id="modal-coords">0.0000, 0.0000</p>
                </div>
              </div>
              <button id="close-modal-x" class="w-8 h-8 rounded-full bg-[var(--bg)] flex items-center justify-center hover:bg-[var(--border)] transition-colors">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
              </button>
            </div>
          </div>

          <!-- Modal Body -->
          <form id="post-form" class="p-5 space-y-4 overflow-y-auto">
            <!-- Note Input -->
            <div>
              <div class="rounded-2xl bg-[var(--bg)] border border-[var(--border)] p-3">
                <textarea 
                  id="post-note"
                  rows="3"
                  maxlength="280"
                  placeholder="What's happening here?"
                  class="w-full bg-transparent text-[var(--fg)] placeholder:text-[var(--muted)] resize-none text-sm focus:outline-none"
                ></textarea>

                <div id="photo-placeholder" class="mt-2 text-xs text-[var(--muted)] flex items-center gap-2">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <polyline points="21 15 16 10 5 21"></polyline>
                  </svg>
                  <span>No photo selected</span>
                </div>

                <div id="photo-preview" class="hidden mt-2">
                  <img id="preview-img" class="max-h-24 w-auto rounded-xl" alt="Preview">
                  <button type="button" id="remove-photo" class="mt-1 text-xs text-[var(--accent)] hover:underline">Remove photo</button>
                </div>

                <div class="mt-3 flex items-center justify-between">
                  <button
                    type="button"
                    id="photo-upload-area"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[var(--card)] border border-[var(--border)] text-xs font-medium hover:border-[var(--accent)] hover:text-[var(--accent)] transition-colors"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                      <circle cx="8.5" cy="8.5" r="1.5"></circle>
                      <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    Add Photo
                  </button>
                  <input type="file" id="photo-input" accept="image/*" class="hidden">
                  <p class="text-right text-xs text-[var(--muted)]"><span id="char-count">0</span>/280</p>
                </div>
              </div>
            </div>



            <!-- Music Search -->
            <div>
              <label class="block text-sm font-medium mb-2 flex items-center gap-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--lavender)" stroke-width="2">
                  <path d="M9 18V5l12-2v13"></path>
                  <circle cx="6" cy="18" r="3"></circle>
                  <circle cx="18" cy="16" r="3"></circle>
                </svg>
                Add Music (Search Song)
              </label>
              <input 
                type="text"
                id="song-search"
                placeholder="Search song or artist..."
                autocomplete="off"
                class="w-full px-4 py-3 rounded-2xl bg-[var(--bg)] border border-[var(--border)] text-[var(--fg)] placeholder:text-[var(--muted)] text-sm transition-colors focus:border-[var(--lavender)]"
              >
              <div id="song-results" class="mt-2 hidden max-h-44 overflow-y-auto rounded-2xl border border-[var(--border)] bg-[var(--card)]"></div>
              <div id="selected-song" class="mt-2 hidden rounded-2xl border border-[var(--border)] bg-[var(--lavender-soft)] p-3">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="text-[11px] text-[var(--muted)]">Selected song</p>
                    <p id="selected-song-title" class="text-sm font-semibold truncate"></p>
                    <p id="selected-song-artist" class="text-xs text-[var(--muted)] truncate"></p>
                  </div>
                  <button type="button" id="clear-song" class="text-xs text-[var(--accent)] hover:underline">Clear</button>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-3 pt-2">
              <button 
                type="button"
                id="cancel-post"
                class="w-full sm:flex-1 py-3 px-5 rounded-2xl border border-[var(--border)] bg-[var(--bg)] font-semibold transition-colors hover:bg-[var(--border)]"
              >
                Cancel
              </button>
              <button 
                type="submit"
                class="w-full sm:flex-1 py-3 px-5 rounded-2xl bg-gradient-to-r from-[var(--accent)] to-[#ff8a65] text-white font-semibold shadow-lg shadow-[var(--accent)]/25 transition-all hover:shadow-xl active:scale-[0.98]"
              >
                Post Vibe
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Profile Modal -->
    <div id="profile-modal" class="fixed inset-0 z-50 hidden">
      <div id="profile-modal-backdrop" class="absolute inset-0 bg-black/35 backdrop-blur-sm animate-fade-in"></div>
      <div class="absolute inset-0 flex items-start sm:items-center justify-center p-0 sm:p-4 overflow-y-auto">
        <div class="my-0 bg-[var(--card)] rounded-none sm:rounded-3xl w-full sm:max-w-md h-full sm:h-auto shadow-2xl animate-scale-in overflow-hidden sm:max-h-[calc(100dvh-2rem)] flex flex-col">
          <div class="p-5 border-b border-[var(--border)] bg-gradient-to-b from-[var(--bg-warm)] to-[var(--card)] flex items-center justify-between shrink-0">
            <div>
              <p class="font-semibold">Edit Profile Picture</p>
              <p class="text-xs text-[var(--muted)]">Upload a new photo for your profile</p>
            </div>
            <button id="close-profile-modal-x" class="w-8 h-8 rounded-full bg-[var(--bg)] flex items-center justify-center hover:bg-[var(--border)] transition-colors" type="button">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>
          <form id="profile-form" class="p-5 space-y-4 overflow-y-auto">
            <div class="flex items-center gap-4">
              <div id="profile-avatar-preview" class="w-20 h-20 rounded-full overflow-hidden bg-gradient-to-br from-[var(--mint)] to-[var(--lavender)] flex items-center justify-center text-white text-xl font-semibold">U</div>
              <div class="flex-1 space-y-2">
                <button id="choose-profile-avatar-btn" type="button" class="w-full py-2 rounded-xl border border-[var(--border)] text-sm font-medium hover:border-[var(--mint)] hover:text-[var(--mint)] transition-colors">Choose Photo</button>
                <button id="remove-profile-avatar-btn" type="button" class="w-full py-2 rounded-xl border border-[var(--border)] text-sm font-medium hover:border-[var(--accent)] hover:text-[var(--accent)] transition-colors">Remove Photo</button>
                <input id="profile-avatar-input" type="file" accept="image/*" class="hidden">
              </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
              <button id="cancel-profile-btn" type="button" class="w-full sm:flex-1 py-2.5 rounded-xl border border-[var(--border)] bg-[var(--bg)] text-sm font-semibold hover:bg-[var(--border)] transition-colors">Cancel</button>
              <button type="submit" class="w-full sm:flex-1 py-2.5 rounded-xl bg-gradient-to-r from-[var(--mint)] to-[#5cd4cb] text-white text-sm font-semibold shadow-lg shadow-[var(--mint)]/25">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Bootstrap Alert/Confirm Modal -->
    <div class="modal fade" id="app-dialog-modal" tabindex="-1" aria-labelledby="app-dialog-title" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-semibold" id="app-dialog-title">Notice</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body pt-2">
            <p id="app-dialog-message" class="mb-0 text-body"></p>
          </div>
          <div class="modal-footer border-0 pt-2">
            <button type="button" id="app-dialog-cancel" class="btn btn-outline-secondary d-none">Cancel</button>
            <button type="button" id="app-dialog-ok" class="btn btn-primary px-4">OK</button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    window.__VIBEMAP_INITIAL_STORAGE__ = <?= isset($storageJson) && $storageJson !== '' ? $storageJson : '{}' ?>;
  </script>
  <script src="storage-shim.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (function () {
      const storage = window.__vibemapStorage || window.localStorage;
      const AUTH_STORAGE_KEY = 'vibemap_auth_user';
      const AUTH_NAME_STORAGE_KEY = 'vibemap_auth_name';
      const PROFILE_CACHE_KEY = 'vibemap_profiles';

      const currentUser = (storage.getItem(AUTH_STORAGE_KEY) || '').trim();
      if (!currentUser) {
        window.location.replace('login.php');
        return;
      }
      const storedAuthName = (storage.getItem(AUTH_NAME_STORAGE_KEY) || '').trim();

      function deriveDisplayNameFromEmail(email) {
        const value = (email || '').trim();
        if (!value.includes('@')) {
          return value || 'User';
        }

        const localPart = value.split('@')[0].replace(/[._-]+/g, ' ').trim();
        if (!localPart) {
          return 'User';
        }

        return localPart
          .split(/\s+/)
          .filter(Boolean)
          .map((chunk) => chunk.charAt(0).toUpperCase() + chunk.slice(1))
          .join(' ');
      }

      const currentUserName = storedAuthName && storedAuthName.toLowerCase() !== currentUser.toLowerCase()
        ? storedAuthName
        : deriveDisplayNameFromEmail(currentUser);

      if (typeof window.L === 'undefined') {
        return;
      }

      const dom = {
        map: document.getElementById('map'),
        addPostBtn: document.getElementById('add-post-btn'),
        locateBtn: document.getElementById('locate-btn'),
        postModal: document.getElementById('post-modal'),
        modalBackdrop: document.getElementById('modal-backdrop'),
        postModalAvatar: document.getElementById('post-modal-avatar'),
        modalCoords: document.getElementById('modal-coords'),
        closeModalX: document.getElementById('close-modal-x'),
        postForm: document.getElementById('post-form'),
        postNote: document.getElementById('post-note'),
        charCount: document.getElementById('char-count'),
        photoUploadArea: document.getElementById('photo-upload-area'),
        photoInput: document.getElementById('photo-input'),
        photoPlaceholder: document.getElementById('photo-placeholder'),
        photoPreview: document.getElementById('photo-preview'),
        previewImg: document.getElementById('preview-img'),
        removePhoto: document.getElementById('remove-photo'),
        songSearch: document.getElementById('song-search'),
        songResults: document.getElementById('song-results'),
        selectedSong: document.getElementById('selected-song'),
        selectedSongTitle: document.getElementById('selected-song-title'),
        selectedSongArtist: document.getElementById('selected-song-artist'),
        clearSong: document.getElementById('clear-song'),
        cancelPost: document.getElementById('cancel-post'),
        sidebar: document.getElementById('sidebar'),
        sidebarBackdrop: document.getElementById('sidebar-backdrop'),
        sidebarToggleBtn: document.getElementById('sidebar-toggle-btn'),
        sidebarCloseBtn: document.getElementById('sidebar-close-btn'),
        sidebarAvatar: document.getElementById('sidebar-avatar'),
        sidebarFullName: document.getElementById('sidebar-full-name'),
        sidebarUsername: document.getElementById('sidebar-username'),
        sidebarProfileBtn: document.getElementById('sidebar-profile-btn'),
        sidebarLogoutBtn: document.getElementById('sidebar-logout-btn'),
        profileModal: document.getElementById('profile-modal'),
        profileModalBackdrop: document.getElementById('profile-modal-backdrop'),
        closeProfileModalX: document.getElementById('close-profile-modal-x'),
        profileForm: document.getElementById('profile-form'),
        profileAvatarPreview: document.getElementById('profile-avatar-preview'),
        chooseProfileAvatarBtn: document.getElementById('choose-profile-avatar-btn'),
        removeProfileAvatarBtn: document.getElementById('remove-profile-avatar-btn'),
        profileAvatarInput: document.getElementById('profile-avatar-input'),
        cancelProfileBtn: document.getElementById('cancel-profile-btn'),
        appDialogModal: document.getElementById('app-dialog-modal'),
        appDialogTitle: document.getElementById('app-dialog-title'),
        appDialogMessage: document.getElementById('app-dialog-message'),
        appDialogOk: document.getElementById('app-dialog-ok'),
        appDialogCancel: document.getElementById('app-dialog-cancel')
      };

      if (!dom.map) {
        return;
      }

      let map;
      let userMarker = null;
      let userCoords = null;
      let pendingPostCoords = null;
      let selectedPhotoDataUrl = null;
      let selectedSong = null;
      let songSuggestions = [];
      let songSearchTimer = null;
      let songSearchRequestId = 0;
      let pendingProfileAvatar = null;
      let profiles = {};
      let posts = [];
      let initialViewSet = false;
      const postMarkers = new Map();
      let activePopupAudio = null;
      let appDialog = null;
      const MAX_POST_PHOTO_DATA_URL_LENGTH = 650000;
      const MAX_PROFILE_AVATAR_DATA_URL_LENGTH = 420000;
      const MAX_POST_PAYLOAD_LENGTH = 900000;

      function normalizeUser(value) {
        return typeof value === 'string' ? value.trim().toLowerCase() : '';
      }

      function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
      }

      function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
      }

      function parseStoredObject(key) {
        try {
          const parsed = JSON.parse(storage.getItem(key) || '{}');
          return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (_) {
          return {};
        }
      }

      function hasBootstrapModalSupport() {
        return typeof window.bootstrap === 'object'
          && window.bootstrap !== null
          && typeof window.bootstrap.Modal === 'function';
      }

      function showAppDialog(options) {
        const title = options && typeof options.title === 'string' && options.title.trim()
          ? options.title.trim()
          : 'Notice';
        const message = options && typeof options.message === 'string' && options.message
          ? options.message
          : 'Something went wrong.';
        const isConfirm = Boolean(options && options.confirm);
        const okLabel = options && typeof options.okLabel === 'string' && options.okLabel.trim()
          ? options.okLabel.trim()
          : 'OK';
        const cancelLabel = options && typeof options.cancelLabel === 'string' && options.cancelLabel.trim()
          ? options.cancelLabel.trim()
          : 'Cancel';

        if (!hasBootstrapModalSupport()
          || !dom.appDialogModal
          || !dom.appDialogTitle
          || !dom.appDialogMessage
          || !dom.appDialogOk
          || !dom.appDialogCancel) {
          if (isConfirm) {
            return Promise.resolve(window.confirm(message));
          }
          window.alert(message);
          return Promise.resolve(true);
        }

        dom.appDialogTitle.textContent = title;
        dom.appDialogMessage.textContent = message;
        dom.appDialogOk.textContent = okLabel;
        dom.appDialogCancel.textContent = cancelLabel;
        dom.appDialogCancel.classList.toggle('d-none', !isConfirm);

        if (!appDialog) {
          appDialog = new window.bootstrap.Modal(dom.appDialogModal, {
            backdrop: 'static',
            keyboard: true
          });
        }

        return new Promise((resolve) => {
          let settled = false;

          const cleanup = () => {
            dom.appDialogOk.removeEventListener('click', onOk);
            dom.appDialogCancel.removeEventListener('click', onCancel);
            dom.appDialogModal.removeEventListener('hidden.bs.modal', onHidden);
          };

          const settle = (value) => {
            if (settled) {
              return;
            }
            settled = true;
            cleanup();
            resolve(value);
          };

          const onOk = () => {
            settle(true);
            appDialog.hide();
          };
          const onCancel = () => {
            settle(false);
            appDialog.hide();
          };
          const onHidden = () => {
            settle(isConfirm ? false : true);
          };

          dom.appDialogOk.addEventListener('click', onOk);
          dom.appDialogCancel.addEventListener('click', onCancel);
          dom.appDialogModal.addEventListener('hidden.bs.modal', onHidden);

          appDialog.show();
        });
      }

      function showAlertDialog(message, title) {
        return showAppDialog({ message, title, confirm: false });
      }

      function showConfirmDialog(message, title, okLabel) {
        return showAppDialog({
          message,
          title,
          confirm: true,
          okLabel: okLabel || 'OK',
          cancelLabel: 'Cancel'
        });
      }

      function getUserInitial(username) {
        const value = (username || '').trim();
        return (value.charAt(0) || 'U').toUpperCase();
      }

      function getAvatarForUser(username) {
        if (!username) return null;
        const profile = profiles[username];
        if (!profile || typeof profile !== 'object') return null;
        return typeof profile.avatar === 'string' && profile.avatar ? profile.avatar : null;
      }

      function setElementAvatar(element, username, sizeClass) {
        if (!element) return;
        const avatarUrl = getAvatarForUser(username);
        if (avatarUrl) {
          element.innerHTML = `<img src="${escapeAttr(avatarUrl)}" alt="Profile picture" class="w-full h-full object-cover">`;
          return;
        }
        element.textContent = getUserInitial(username);
        if (sizeClass) {
          element.classList.add(sizeClass);
        }
      }

      function renderSidebarAccount() {
        if (dom.sidebarFullName) {
          dom.sidebarFullName.textContent = currentUserName || 'User';
        }
        if (dom.sidebarUsername) {
          dom.sidebarUsername.textContent = currentUser || '';
        }
        setElementAvatar(dom.sidebarAvatar, currentUser);
      }

      function logoutUser() {
        try {
          storage.removeItem(AUTH_STORAGE_KEY);
          storage.removeItem(AUTH_NAME_STORAGE_KEY);
        } catch (_) {}
        window.location.replace('login.php');
      }

      function isDesktopViewport() {
        return window.matchMedia('(min-width: 768px)').matches;
      }

      function closeSidebar() {
        if (!dom.sidebar) return;
        if (isDesktopViewport()) {
          dom.sidebar.classList.remove('-translate-x-full');
        } else {
          dom.sidebar.classList.add('-translate-x-full');
        }
        if (dom.sidebarBackdrop) {
          dom.sidebarBackdrop.classList.add('hidden');
        }
      }

      function openSidebar() {
        if (!dom.sidebar || isDesktopViewport()) return;
        dom.sidebar.classList.remove('-translate-x-full');
        if (dom.sidebarBackdrop) {
          dom.sidebarBackdrop.classList.remove('hidden');
        }
      }

      function toggleSidebar() {
        if (!dom.sidebar || isDesktopViewport()) return;
        if (dom.sidebar.classList.contains('-translate-x-full')) {
          openSidebar();
          return;
        }
        closeSidebar();
      }

      function formatTimestamp(value) {
        const parsed = Number(value);
        if (!Number.isFinite(parsed) || parsed <= 0) return '';
        try {
          return new Date(parsed).toLocaleString();
        } catch (_) {
          return '';
        }
      }

      function formatCoords(lat, lng) {
        return `${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}`;
      }

      function markerColorForUser(username) {
        const palette = ['#ff6b4a', '#4ecdc4', '#a78bfa', '#3b82f6', '#f59e0b'];
        const value = normalizeUser(username);
        let hash = 0;
        for (let i = 0; i < value.length; i += 1) {
          hash = (hash << 5) - hash + value.charCodeAt(i);
          hash |= 0;
        }
        return palette[Math.abs(hash) % palette.length];
      }

      function buildPostIcon(post) {
        const ownerKey = typeof post.username === 'string' ? post.username : '';
        const displayName = typeof post.fullName === 'string' && post.fullName.trim()
          ? post.fullName.trim()
          : (ownerKey || 'User');
        const avatar = getAvatarForUser(ownerKey);
        if (avatar) {
          return `
            <div class="vibe-marker">
              <div class="marker-avatar-wrap">
                <img src="${escapeAttr(avatar)}" alt="${escapeAttr(displayName)}" class="marker-avatar">
              </div>
              <div class="marker-pulse marker-pulse-circle" style="background:${markerColorForUser(ownerKey || displayName)};"></div>
            </div>
          `;
        }

        const color = markerColorForUser(ownerKey || displayName);
        return `
          <div class="vibe-marker">
            <div class="marker-outer" style="background:${color};">
              <div class="marker-inner"></div>
            </div>
            <div class="marker-pulse" style="background:${color};"></div>
          </div>
        `;
      }

      function getMusicPreviewUrl(music) {
        const previewUrl = music && typeof music.previewUrl === 'string'
          ? music.previewUrl.trim()
          : '';
        if (!previewUrl || !/^https?:\/\//i.test(previewUrl)) {
          return '';
        }
        return previewUrl;
      }

      function buildPostPopup(post) {
        const ownerKey = typeof post.username === 'string' && post.username ? post.username : '';
        const displayName = typeof post.fullName === 'string' && post.fullName.trim()
          ? post.fullName.trim()
          : (ownerKey || 'User');
        const timestamp = formatTimestamp(post.timestamp);
        const note = typeof post.note === 'string' ? post.note.trim() : '';
        const normalizedNote = note.replace(/\n{3,}/g, '\n\n');
        const photo = typeof post.photo === 'string' && post.photo ? post.photo : null;
        const music = post.music && typeof post.music === 'object' ? post.music : null;
        const isMine = normalizeUser(ownerKey) === normalizeUser(currentUser);
        const postId = String(post.id || '');
        const views = Math.max(0, Math.floor(Number(post.views) || 0));

        const musicTitle = music && typeof music.title === 'string' ? music.title.trim() : '';
        const musicArtist = music && typeof music.artist === 'string' ? music.artist.trim() : '';
        const musicPreviewUrl = getMusicPreviewUrl(music);
        const hasMusic = musicTitle !== '' || musicArtist !== '';
        const hasContent = normalizedNote !== '' || photo !== null || hasMusic;

        return `
          <div class="popup-post-card" data-popup-post-id="${escapeAttr(postId)}">
            <div class="popup-post-header">
              ${getAvatarForUser(ownerKey)
                ? `<div class="popup-post-avatar"><img src="${escapeAttr(getAvatarForUser(ownerKey))}" alt="Profile picture" class="w-full h-full object-cover"></div>`
                : `<div class="popup-post-avatar popup-post-avatar-fallback">${escapeHtml(getUserInitial(displayName))}</div>`
              }
              <div class="popup-post-meta">
                <p class="popup-post-username truncate">${escapeHtml(displayName)}</p>
                <p class="popup-post-time">${escapeHtml(timestamp || 'Just now')}</p>
              </div>
            </div>

            <div class="popup-post-content">
              ${hasContent ? '' : '<p class="popup-post-empty">No post content.</p>'}
              ${normalizedNote ? `<p class="popup-post-note">${escapeHtml(normalizedNote)}</p>` : ''}
              ${photo ? `<img src="${escapeAttr(photo)}" alt="Post photo" class="popup-post-photo rounded-xl border border-[var(--border)]">` : ''}
              ${hasMusic ? `
              <div class="music-card">
                <div class="music-art">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <path d="M9 18V5l12-2v13"></path>
                    <circle cx="6" cy="18" r="3"></circle>
                    <circle cx="18" cy="16" r="3"></circle>
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-semibold truncate">${escapeHtml(musicTitle || 'Untitled track')}</p>
                  <p class="text-[11px] text-[var(--muted)] truncate">${escapeHtml(musicArtist || 'Unknown artist')}</p>
                </div>
                ${musicPreviewUrl
                  ? `<audio data-post-preview="true" class="music-preview-player mt-2" controls playsinline preload="none" src="${escapeAttr(musicPreviewUrl)}"></audio>`
                  : ''
                }
              </div>
            ` : ''}
            </div>

            <div class="popup-post-actions">
              <div class="popup-views-chip">
                <svg class="popup-views-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <span data-post-views="true" class="popup-views-value">${escapeHtml(String(views))}</span>
              </div>
              ${isMine
                ? `
                  <button type="button" data-action="delete-post" data-post-id="${escapeAttr(postId)}" class="popup-delete-btn" aria-label="Delete post" title="Delete post">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="3 6 5 6 21 6"></polyline>
                      <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                      <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                      <line x1="10" y1="11" x2="10" y2="17"></line>
                      <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                  </button>
                `
                : ''
              }
            </div>
          </div>
        `;
      }

      function stopActivePopupAudio() {
        if (!activePopupAudio) {
          return;
        }
        try {
          activePopupAudio.pause();
          activePopupAudio.currentTime = 0;
        } catch (_) {}
        activePopupAudio = null;
      }

      function autoplayPopupMusic(event) {
        stopActivePopupAudio();

        const popupElement = event && event.popup && typeof event.popup.getElement === 'function'
          ? event.popup.getElement()
          : null;
        if (!popupElement) {
          return;
        }

        const audio = popupElement.querySelector('audio[data-post-preview="true"]');
        if (!audio) {
          return;
        }

        activePopupAudio = audio;
        try {
          audio.currentTime = 0;
          const playPromise = audio.play();
          if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(() => {});
          }
        } catch (_) {}
      }

      function clearMarkers() {
        stopActivePopupAudio();
        postMarkers.forEach((marker) => {
          map.removeLayer(marker);
        });
        postMarkers.clear();
      }

      function currentUserHasPost() {
        return posts.some((post) => normalizeUser(post.username) === normalizeUser(currentUser));
      }

      function syncUserMarkerVisibility() {
        if (!map) return;

        if (currentUserHasPost()) {
          if (userMarker) {
            map.removeLayer(userMarker);
            userMarker = null;
          }
          return;
        }

        if (userCoords && Number.isFinite(userCoords.lat) && Number.isFinite(userCoords.lng)) {
          setUserMarker(userCoords.lat, userCoords.lng);
        }
      }

      async function incrementPostViews(postId, popupElement) {
        if (!postId) return;

        try {
          const response = await fetchJson('posts-api.php', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              id: String(postId),
              viewer: currentUser
            })
          });

          if (!response || response.ok !== true) {
            return;
          }

          const updatedViews = Math.max(0, Math.floor(Number(response.views) || 0));
          const targetPost = posts.find((item) => String(item.id) === String(postId));
          if (targetPost) {
            targetPost.views = updatedViews;
          }

          if (popupElement) {
            const viewNode = popupElement.querySelector('[data-post-views="true"]');
            if (viewNode) {
              viewNode.textContent = String(updatedViews);
            }
          }
        } catch (_) {}
      }

      function trackPopupView(event) {
        const popupElement = event && event.popup && typeof event.popup.getElement === 'function'
          ? event.popup.getElement()
          : null;
        if (!popupElement) return;

        const postCard = popupElement.querySelector('[data-popup-post-id]');
        const postId = postCard ? (postCard.getAttribute('data-popup-post-id') || '').trim() : '';
        if (!postId) return;

        incrementPostViews(postId, popupElement);
      }

      function setInitialViewportFromPosts() {
        if (initialViewSet || userCoords) return;

        const points = posts
          .filter((post) => Number.isFinite(Number(post.lat)) && Number.isFinite(Number(post.lng)))
          .map((post) => [Number(post.lat), Number(post.lng)]);

        if (points.length === 0) {
          return;
        }

        if (points.length === 1) {
          map.setView(points[0], 13);
          initialViewSet = true;
          return;
        }

        map.fitBounds(points, { padding: [60, 60], maxZoom: 13 });
        initialViewSet = true;
      }

      function renderPosts() {
        clearMarkers();

        posts.forEach((post) => {
          const lat = Number(post.lat);
          const lng = Number(post.lng);
          if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
          }

          const marker = L.marker([lat, lng], {
            icon: L.divIcon({
              className: '',
              html: buildPostIcon(post),
              iconSize: [44, 44],
              iconAnchor: [22, 42],
              popupAnchor: [0, -35]
            })
          });

          marker.bindPopup(buildPostPopup(post), { maxWidth: 300 });
          marker.__postId = String(post.id || '');
          marker.addTo(map);
          postMarkers.set(String(post.id || ''), marker);
        });

        syncUserMarkerVisibility();
        setInitialViewportFromPosts();
      }

      async function fetchJson(url, options) {
        const response = await fetch(url, options);
        const text = await response.text();
        let payload = {};
        try {
          payload = text ? JSON.parse(text) : {};
        } catch (_) {
          payload = {};
        }

        if (!response.ok) {
          const error = new Error(payload.error || 'request_failed');
          error.payload = payload;
          throw error;
        }

        return payload;
      }

      async function loadProfiles() {
        try {
          const response = await fetchJson('profile-api.php', { method: 'GET', cache: 'no-store' });
          if (response && response.ok && response.profiles && typeof response.profiles === 'object') {
            profiles = response.profiles;
            storage.setItem(PROFILE_CACHE_KEY, JSON.stringify(profiles));
            setElementAvatar(dom.postModalAvatar, currentUser);
            setProfilePreviewAvatar(pendingProfileAvatar);
            renderSidebarAccount();
            return;
          }
        } catch (_) {}

        profiles = parseStoredObject(PROFILE_CACHE_KEY);
        setElementAvatar(dom.postModalAvatar, currentUser);
        setProfilePreviewAvatar(pendingProfileAvatar);
        renderSidebarAccount();
      }

      async function loadPosts() {
        try {
          const response = await fetchJson('posts-api.php', { method: 'GET', cache: 'no-store' });
          posts = Array.isArray(response.posts) ? response.posts : [];
        } catch (_) {
          posts = [];
        }

        renderPosts();
      }

      function setUserMarker(lat, lng) {
        if (currentUserHasPost()) {
          if (userMarker) {
            map.removeLayer(userMarker);
            userMarker = null;
          }
          return;
        }

        const icon = L.divIcon({
          className: '',
          html: `
            <div class="user-location-marker">
              <div class="user-pulse"></div>
              <div class="user-dot"></div>
            </div>
          `,
          iconSize: [40, 40],
          iconAnchor: [20, 20]
        });

        if (userMarker) {
          userMarker.setLatLng([lat, lng]);
        } else {
          userMarker = L.marker([lat, lng], { icon, zIndexOffset: 2000 }).addTo(map);
        }
      }

      function locateUser(centerOnUser) {
        if (!navigator.geolocation) {
          return;
        }

        dom.locateBtn.classList.add('locating');
        navigator.geolocation.getCurrentPosition(
          (position) => {
            dom.locateBtn.classList.remove('locating');
            const lat = Number(position.coords.latitude);
            const lng = Number(position.coords.longitude);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
              return;
            }

            userCoords = { lat, lng };
            pendingPostCoords = { lat, lng };
            setUserMarker(lat, lng);
            if (centerOnUser) {
              map.flyTo([lat, lng], 15, { duration: 0.8 });
            }
            updateModalCoordsLabel();
          },
          () => {
            dom.locateBtn.classList.remove('locating');
            updateModalCoordsLabel();
          },
          {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 90000
          }
        );
      }

      function currentPostCoords() {
        if (pendingPostCoords && Number.isFinite(pendingPostCoords.lat) && Number.isFinite(pendingPostCoords.lng)) {
          return pendingPostCoords;
        }

        if (userCoords && Number.isFinite(userCoords.lat) && Number.isFinite(userCoords.lng)) {
          return userCoords;
        }

        if (!map || typeof map.getCenter !== 'function') {
          return { lat: 14.5995, lng: 120.9842 };
        }

        const center = map.getCenter();
        return { lat: center.lat, lng: center.lng };
      }

      function updateModalCoordsLabel() {
        const coords = currentPostCoords();
        if (dom.modalCoords) {
          dom.modalCoords.textContent = formatCoords(coords.lat, coords.lng);
        }
      }

      function showPostModal() {
        pendingPostCoords = userCoords || currentPostCoords();
        updateModalCoordsLabel();
        setElementAvatar(dom.postModalAvatar, currentUser);
        dom.postModal.classList.remove('hidden');
        if (dom.postNote) {
          dom.postNote.focus();
        }
      }

      function hidePostModal() {
        dom.postModal.classList.add('hidden');
      }

      function resetPostComposer() {
        if (dom.postForm) dom.postForm.reset();
        selectedPhotoDataUrl = null;
        selectedSong = null;
        updatePhotoPreview();
        renderSelectedSong();
        updateCharCount();
      }

      function updateCharCount() {
        if (!dom.postNote || !dom.charCount) return;
        dom.charCount.textContent = String(dom.postNote.value.length);
      }

      function updatePhotoPreview() {
        if (!dom.photoPreview || !dom.photoPlaceholder || !dom.previewImg) return;

        if (selectedPhotoDataUrl) {
          dom.previewImg.src = selectedPhotoDataUrl;
          dom.photoPreview.classList.remove('hidden');
          dom.photoPlaceholder.classList.add('hidden');
          return;
        }

        dom.previewImg.removeAttribute('src');
        dom.photoPreview.classList.add('hidden');
        dom.photoPlaceholder.classList.remove('hidden');
      }

      function parseSongText(text) {
        const value = text.trim();
        if (!value) return null;
        const parts = value.split(' - ');
        const title = parts[0] ? parts[0].trim() : '';
        const artist = parts.length > 1 ? parts.slice(1).join(' - ').trim() : '';
        if (!title) return null;
        return { title, artist };
      }

      function renderSelectedSong() {
        if (!dom.selectedSong || !dom.selectedSongTitle || !dom.selectedSongArtist) {
          return;
        }

        if (!selectedSong) {
          dom.selectedSong.classList.add('hidden');
          dom.selectedSongTitle.textContent = '';
          dom.selectedSongArtist.textContent = '';
          return;
        }

        dom.selectedSongTitle.textContent = selectedSong.title || 'Untitled track';
        dom.selectedSongArtist.textContent = selectedSong.artist || 'Unknown artist';
        dom.selectedSong.classList.remove('hidden');
      }

      function renderSongSuggestions(tracks) {
        if (!dom.songResults || !dom.songSearch) {
          return;
        }

        songSuggestions = Array.isArray(tracks) ? tracks.slice(0, 8) : [];
        if (songSuggestions.length === 0) {
          dom.songResults.innerHTML = '';
          dom.songResults.classList.add('hidden');
          return;
        }

        dom.songResults.innerHTML = songSuggestions
          .map((track, index) => {
            const title = typeof track.title === 'string' ? track.title.trim() : '';
            const artist = typeof track.artist === 'string' ? track.artist.trim() : '';
            return `
              <button type="button" data-action="choose-song" data-song-index="${index}" class="w-full text-left px-3 py-2 hover:bg-[var(--bg)] transition-colors border-b last:border-b-0 border-[var(--border)]">
                <p class="text-sm font-semibold truncate">${escapeHtml(title || 'Untitled track')}</p>
                <p class="text-xs text-[var(--muted)] truncate">${escapeHtml(artist || 'Unknown artist')}</p>
              </button>
            `;
          })
          .join('');
        dom.songResults.classList.remove('hidden');
      }

      function renderSongSearchLoading() {
        if (!dom.songResults) {
          return;
        }

        dom.songResults.innerHTML = `
          <div class="px-3 py-2 text-xs text-[var(--muted)]">Searching tracks...</div>
        `;
        dom.songResults.classList.remove('hidden');
      }

      async function requestSongSuggestions(query, requestId) {
        try {
          const response = await fetchJson(`music-api.php?q=${encodeURIComponent(query)}&limit=8`, {
            method: 'GET',
            cache: 'no-store'
          });
          if (requestId !== songSearchRequestId) {
            return;
          }
          renderSongSuggestions(Array.isArray(response.tracks) ? response.tracks : []);
        } catch (_) {
          if (requestId !== songSearchRequestId) {
            return;
          }
          const parsed = parseSongText(query);
          if (parsed) {
            renderSongSuggestions([parsed]);
            return;
          }
          renderSongSuggestions([]);
        }
      }

      function handleSongSearchInput() {
        if (!dom.songSearch || !dom.songResults) {
          return;
        }

        const query = (dom.songSearch.value || '').trim();
        if (songSearchTimer) {
          clearTimeout(songSearchTimer);
          songSearchTimer = null;
        }

        if (!query) {
          songSearchRequestId += 1;
          songSuggestions = [];
          dom.songResults.innerHTML = '';
          dom.songResults.classList.add('hidden');
          return;
        }

        renderSongSearchLoading();
        songSearchTimer = setTimeout(() => {
          songSearchTimer = null;
          songSearchRequestId += 1;
          const requestId = songSearchRequestId;
          requestSongSuggestions(query, requestId);
        }, 250);
      }

      function getPostSaveErrorMessage(error) {
        const code = error && error.payload && typeof error.payload.error === 'string'
          ? error.payload.error
          : '';

        if (code === 'photo_too_large' || code === 'payload_too_large' || code === 'invalid_payload') {
          return 'Photo is too large. Please choose a smaller image.';
        }
        if (code === 'invalid_post_data') {
          return 'Your post data is invalid. Please try again.';
        }
        return 'Unable to save your vibe right now.';
      }

      function getProfileSaveErrorMessage(error) {
        const code = error && error.payload && typeof error.payload.error === 'string'
          ? error.payload.error
          : '';

        if (code === 'avatar_too_large' || code === 'payload_too_large' || code === 'invalid_payload') {
          return 'Profile photo is too large. Please choose a smaller image.';
        }
        if (code === 'invalid_username') {
          return 'Unable to identify your account. Please log in again.';
        }
        return 'Unable to save profile right now.';
      }

      async function loadImageElement(file) {
        return new Promise((resolve, reject) => {
          const objectUrl = URL.createObjectURL(file);
          const image = new Image();
          image.onload = () => {
            URL.revokeObjectURL(objectUrl);
            resolve(image);
          };
          image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('invalid_image'));
          };
          image.src = objectUrl;
        });
      }

      async function readOptimizedPostPhoto(file) {
        if (!file || typeof file !== 'object') {
          throw new Error('Please choose an image file.');
        }
        if (typeof file.type === 'string' && file.type !== '' && !file.type.startsWith('image/')) {
          throw new Error('Please choose an image file.');
        }
        if (Number.isFinite(file.size) && file.size > 20 * 1024 * 1024) {
          throw new Error('Photo is too large. Please choose a smaller image.');
        }

        let image;
        try {
          image = await loadImageElement(file);
        } catch (_) {
          throw new Error('Unable to read this image.');
        }
        const originalWidth = Number(image.naturalWidth || image.width);
        const originalHeight = Number(image.naturalHeight || image.height);
        if (!Number.isFinite(originalWidth) || !Number.isFinite(originalHeight) || originalWidth <= 0 || originalHeight <= 0) {
          throw new Error('Unable to process this image.');
        }

        const maxDimension = 1280;
        const scale = Math.min(1, maxDimension / Math.max(originalWidth, originalHeight));
        let width = Math.max(1, Math.round(originalWidth * scale));
        let height = Math.max(1, Math.round(originalHeight * scale));

        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        if (!context) {
          throw new Error('Unable to process this image.');
        }

        const qualitySteps = [0.88, 0.8, 0.72, 0.64, 0.56, 0.48];
        for (let resizeAttempt = 0; resizeAttempt < 5; resizeAttempt += 1) {
          canvas.width = width;
          canvas.height = height;
          context.clearRect(0, 0, width, height);
          context.drawImage(image, 0, 0, width, height);

          for (let index = 0; index < qualitySteps.length; index += 1) {
            const quality = qualitySteps[index];
            const dataUrl = canvas.toDataURL('image/jpeg', quality);
            if (typeof dataUrl === 'string' && dataUrl.length <= MAX_POST_PHOTO_DATA_URL_LENGTH) {
              return dataUrl;
            }
          }

          const nextWidth = Math.max(1, Math.round(width * 0.82));
          const nextHeight = Math.max(1, Math.round(height * 0.82));
          if (nextWidth === width && nextHeight === height) {
            break;
          }
          width = nextWidth;
          height = nextHeight;
        }

        throw new Error('Photo is too large. Please choose a smaller image.');
      }

      async function readOptimizedProfileAvatar(file) {
        if (!file || typeof file !== 'object') {
          throw new Error('Please choose an image file.');
        }
        if (typeof file.type === 'string' && file.type !== '' && !file.type.startsWith('image/')) {
          throw new Error('Please choose an image file.');
        }
        if (Number.isFinite(file.size) && file.size > 20 * 1024 * 1024) {
          throw new Error('Profile photo is too large. Please choose a smaller image.');
        }

        let image;
        try {
          image = await loadImageElement(file);
        } catch (_) {
          throw new Error('Unable to read this image.');
        }

        const originalWidth = Number(image.naturalWidth || image.width);
        const originalHeight = Number(image.naturalHeight || image.height);
        if (!Number.isFinite(originalWidth) || !Number.isFinite(originalHeight) || originalWidth <= 0 || originalHeight <= 0) {
          throw new Error('Unable to process this image.');
        }

        const maxDimension = 640;
        const scale = Math.min(1, maxDimension / Math.max(originalWidth, originalHeight));
        let width = Math.max(1, Math.round(originalWidth * scale));
        let height = Math.max(1, Math.round(originalHeight * scale));

        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        if (!context) {
          throw new Error('Unable to process this image.');
        }

        const qualitySteps = [0.86, 0.78, 0.7, 0.62, 0.54, 0.46];
        for (let resizeAttempt = 0; resizeAttempt < 5; resizeAttempt += 1) {
          canvas.width = width;
          canvas.height = height;
          context.clearRect(0, 0, width, height);
          context.drawImage(image, 0, 0, width, height);

          for (let index = 0; index < qualitySteps.length; index += 1) {
            const quality = qualitySteps[index];
            const dataUrl = canvas.toDataURL('image/jpeg', quality);
            if (typeof dataUrl === 'string' && dataUrl.length <= MAX_PROFILE_AVATAR_DATA_URL_LENGTH) {
              return dataUrl;
            }
          }

          const nextWidth = Math.max(1, Math.round(width * 0.8));
          const nextHeight = Math.max(1, Math.round(height * 0.8));
          if (nextWidth === width && nextHeight === height) {
            break;
          }
          width = nextWidth;
          height = nextHeight;
        }

        throw new Error('Profile photo is too large. Please choose a smaller image.');
      }

      async function submitPost(event) {
        event.preventDefault();

        const note = dom.postNote ? dom.postNote.value.trim() : '';
        if (!note && !selectedPhotoDataUrl && !selectedSong) {
          await showAlertDialog('Add a note, photo, or song before posting.', 'Post Required');
          return;
        }

        const coords = currentPostCoords();
        const payload = {
          username: currentUser,
          fullName: currentUserName || currentUser,
          id: `post-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
          lat: Number(coords.lat.toFixed(6)),
          lng: Number(coords.lng.toFixed(6)),
          note,
          photo: selectedPhotoDataUrl,
          music: selectedSong,
          timestamp: Date.now()
        };
        const payloadBody = JSON.stringify(payload);
        if (payloadBody.length > MAX_POST_PAYLOAD_LENGTH) {
          await showAlertDialog('Photo is too large. Please choose a smaller image.', 'Image Too Large');
          return;
        }

        try {
          const response = await fetchJson('posts-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: payloadBody
          });

          if (!response || response.ok !== true) {
            throw new Error('save_failed');
          }

          hidePostModal();
          resetPostComposer();
          await loadPosts();
        } catch (error) {
          await showAlertDialog(getPostSaveErrorMessage(error), 'Unable to Post');
        }
      }

      async function deletePost(postId) {
        const post = posts.find((item) => String(item.id) === String(postId));
        if (!post) return;
        if (normalizeUser(post.username) !== normalizeUser(currentUser)) return;
        const confirmed = await showConfirmDialog('Delete this vibe?', 'Delete Vibe', 'Delete');
        if (!confirmed) return;

        try {
          const response = await fetchJson('posts-api.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              username: currentUser,
              id: String(postId)
            })
          });

          if (!response || response.ok !== true) {
            throw new Error('delete_failed');
          }

          await loadPosts();
        } catch (_) {
          await showAlertDialog('Unable to delete this vibe right now.', 'Delete Failed');
        }
      }

      function setProfilePreviewAvatar(avatarUrl) {
        if (!dom.profileAvatarPreview) return;

        if (avatarUrl) {
          dom.profileAvatarPreview.innerHTML = `<img src="${escapeAttr(avatarUrl)}" alt="Profile picture" class="w-full h-full object-cover">`;
          return;
        }

        dom.profileAvatarPreview.textContent = getUserInitial(currentUser);
      }

      function openProfileModal() {
        pendingProfileAvatar = getAvatarForUser(currentUser);
        setProfilePreviewAvatar(pendingProfileAvatar);
        dom.profileModal.classList.remove('hidden');
      }

      function closeProfileModal() {
        dom.profileModal.classList.add('hidden');
      }

      async function submitProfile(event) {
        event.preventDefault();

        try {
          const response = await fetchJson('profile-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              username: currentUser,
              avatar: pendingProfileAvatar
            })
          });

          if (!response || response.ok !== true) {
            throw new Error('save_failed');
          }

          if (pendingProfileAvatar) {
            profiles[currentUser] = { avatar: pendingProfileAvatar };
          } else {
            delete profiles[currentUser];
          }
          storage.setItem(PROFILE_CACHE_KEY, JSON.stringify(profiles));
          setElementAvatar(dom.postModalAvatar, currentUser);
          renderSidebarAccount();
          setProfilePreviewAvatar(pendingProfileAvatar);
          renderPosts();
          closeProfileModal();
        } catch (error) {
          await showAlertDialog(getProfileSaveErrorMessage(error), 'Save Failed');
        }
      }

      function initMap() {
        map = L.map(dom.map, {
          zoomControl: true
        }).setView([14.5995, 120.9842], 12);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}{r}.png', {
          maxZoom: 20,
          subdomains: 'abcd',
          attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
        }).addTo(map);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png', {
          maxZoom: 20,
          subdomains: 'abcd',
          pane: 'overlayPane',
          opacity: 0.88
        }).addTo(map);

        map.on('moveend', () => {
          if (!dom.postModal.classList.contains('hidden')) {
            pendingPostCoords = {
              lat: map.getCenter().lat,
              lng: map.getCenter().lng
            };
            updateModalCoordsLabel();
          }
        });
        map.on('popupopen', autoplayPopupMusic);
        map.on('popupopen', trackPopupView);
        map.on('popupclose', stopActivePopupAudio);
      }

      function registerUiEvents() {
        dom.addPostBtn.addEventListener('click', showPostModal);
        dom.locateBtn.addEventListener('click', () => locateUser(true));

        dom.closeModalX.addEventListener('click', hidePostModal);
        dom.cancelPost.addEventListener('click', hidePostModal);
        dom.modalBackdrop.addEventListener('click', hidePostModal);
        dom.postForm.addEventListener('submit', submitPost);

        dom.postNote.addEventListener('input', updateCharCount);
        dom.photoUploadArea.addEventListener('click', () => dom.photoInput.click());
        dom.photoInput.addEventListener('change', async () => {
          const file = dom.photoInput.files && dom.photoInput.files[0];
          if (!file) return;
          try {
            selectedPhotoDataUrl = await readOptimizedPostPhoto(file);
            updatePhotoPreview();
          } catch (error) {
            selectedPhotoDataUrl = null;
            dom.photoInput.value = '';
            updatePhotoPreview();
            const message = error && typeof error.message === 'string' && error.message
              ? error.message
              : 'Unable to process this image.';
            await showAlertDialog(message, 'Image Error');
          }
        });
        dom.removePhoto.addEventListener('click', () => {
          selectedPhotoDataUrl = null;
          dom.photoInput.value = '';
          updatePhotoPreview();
        });

        dom.songSearch.addEventListener('input', handleSongSearchInput);
        dom.clearSong.addEventListener('click', () => {
          if (songSearchTimer) {
            clearTimeout(songSearchTimer);
            songSearchTimer = null;
          }
          selectedSong = null;
          songSuggestions = [];
          songSearchRequestId += 1;
          dom.songSearch.value = '';
          dom.songResults.innerHTML = '';
          dom.songResults.classList.add('hidden');
          renderSelectedSong();
        });

        if (dom.sidebarToggleBtn) {
          dom.sidebarToggleBtn.addEventListener('click', toggleSidebar);
        }
        if (dom.sidebarCloseBtn) {
          dom.sidebarCloseBtn.addEventListener('click', closeSidebar);
        }
        if (dom.sidebarBackdrop) {
          dom.sidebarBackdrop.addEventListener('click', closeSidebar);
        }

        dom.postModalAvatar.classList.add('cursor-pointer');
        dom.postModalAvatar.title = 'Edit profile photo';
        dom.postModalAvatar.addEventListener('click', openProfileModal);
        if (dom.sidebarProfileBtn) {
          dom.sidebarProfileBtn.addEventListener('click', () => {
            closeSidebar();
            openProfileModal();
          });
        }
        if (dom.sidebarLogoutBtn) {
          dom.sidebarLogoutBtn.addEventListener('click', () => {
            closeSidebar();
            logoutUser();
          });
        }

        dom.closeProfileModalX.addEventListener('click', closeProfileModal);
        dom.profileModalBackdrop.addEventListener('click', closeProfileModal);
        dom.cancelProfileBtn.addEventListener('click', closeProfileModal);
        dom.chooseProfileAvatarBtn.addEventListener('click', () => dom.profileAvatarInput.click());
        dom.profileAvatarInput.addEventListener('change', async () => {
          const file = dom.profileAvatarInput.files && dom.profileAvatarInput.files[0];
          if (!file) return;
          try {
            pendingProfileAvatar = await readOptimizedProfileAvatar(file);
            setProfilePreviewAvatar(pendingProfileAvatar);
          } catch (error) {
            pendingProfileAvatar = getAvatarForUser(currentUser);
            dom.profileAvatarInput.value = '';
            setProfilePreviewAvatar(pendingProfileAvatar);
            const message = error && typeof error.message === 'string' && error.message
              ? error.message
              : 'Unable to process this image.';
            await showAlertDialog(message, 'Image Error');
          }
        });
        dom.removeProfileAvatarBtn.addEventListener('click', () => {
          pendingProfileAvatar = null;
          dom.profileAvatarInput.value = '';
          setProfilePreviewAvatar(null);
        });
        dom.profileForm.addEventListener('submit', submitProfile);

        document.addEventListener('click', (event) => {
          const songButton = event.target.closest('[data-action="choose-song"]');
          if (songButton) {
            if (songSearchTimer) {
              clearTimeout(songSearchTimer);
              songSearchTimer = null;
            }
            songSearchRequestId += 1;
            const index = Number(songButton.getAttribute('data-song-index'));
            const suggestion = Number.isInteger(index) ? songSuggestions[index] : null;
            const parsed = suggestion || parseSongText(dom.songSearch.value || '');
            if (parsed && parsed.title) {
              const previewUrl = getMusicPreviewUrl(suggestion);
              selectedSong = {
                title: parsed.title,
                artist: parsed.artist || ''
              };
              if (previewUrl) {
                selectedSong.previewUrl = previewUrl;
              }
              dom.songSearch.value = selectedSong.artist
                ? `${selectedSong.title} - ${selectedSong.artist}`
                : selectedSong.title;
              renderSelectedSong();
              dom.songResults.classList.add('hidden');
            }
            return;
          }

          const deleteButton = event.target.closest('[data-action="delete-post"]');
          if (deleteButton) {
            const postId = deleteButton.getAttribute('data-post-id');
            if (postId) {
              deletePost(postId);
            }
          }
        });

        window.addEventListener('resize', () => {
          closeSidebar();
          if (map) {
            map.invalidateSize();
          }
        });
      }

      async function boot() {
        initMap();

        profiles = parseStoredObject(PROFILE_CACHE_KEY);
        setElementAvatar(dom.postModalAvatar, currentUser);
        renderSidebarAccount();
        updateModalCoordsLabel();
        updateCharCount();
        updatePhotoPreview();
        renderSelectedSong();

        registerUiEvents();
        await Promise.all([loadProfiles(), loadPosts()]);
        locateUser(true);
      }

      boot();
    })();
  </script>
</body>
</html>
