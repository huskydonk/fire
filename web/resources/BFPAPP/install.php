<?php
// Determine which mode is selected (resident or officer)
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'select';

// Set configuration based on mode
if ($mode === 'officer') {
    $manifest = 'manifest-officer.json';
    $title = 'BFP Officer Hub';
    $subtitle = 'Official Officer Response App';
    $icon = 'https://cdn-icons-png.flaticon.com/512/2237/2237536.png';
    $bgColor = 'bg-gray-900';
    $btnColor = 'bg-blue-700 hover:bg-blue-800';
} else {
    $manifest = 'manifest.json';
    $title = 'BFP Early Alert';
    $subtitle = 'Fire Safety & Response App';
    $icon = 'https://cdn-icons-png.flaticon.com/512/9312/9312231.png';
    $bgColor = 'bg-gray-100';
    $btnColor = 'bg-red-700 hover:bg-red-800';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $mode === 'officer' ? '#1e3a8a' : '#b91c1c'; ?>">
    <meta name="description" content="<?php echo $subtitle; ?>">
    
    <title>Install <?php echo $title; ?></title>
    
    <link rel="manifest" href="<?php echo $manifest; ?>">
    <link rel="icon" type="image/png" href="<?php echo $icon; ?>">
    <link rel="apple-touch-icon" href="<?php echo $icon; ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .pulse-glow { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
    </style>
</head>
<body class="<?php echo $bgColor; ?> min-h-screen flex flex-col items-center justify-center p-6 font-sans transition-colors duration-500">

    <div class="max-w-md w-full text-center space-y-8 fade-in">
        
        <?php if ($mode === 'select'): ?>
            <div class="space-y-6">
                <img src="logoBFP.png" alt="BFP Logo" class="w-24 h-24 mx-auto mb-4 object-contain">
                <h1 class="text-3xl font-extrabold text-gray-800">Select Application</h1>
                <p class="text-gray-500">Choose your version to continue</p>
                
                <div class="grid gap-4 mt-8">
                    <a href="?mode=resident" class="group relative flex items-center p-4 bg-white border-2 border-red-100 rounded-xl shadow-sm hover:border-red-500 hover:shadow-md transition-all">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center text-red-600 mr-4 group-hover:bg-red-600 group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.657 7.343A8 8 0 0118.657 17.657c-1.566 1.566-2.343 3.657-2.343 3.657-1-2-4-2.986-7-2.986 2.5.986 5 .5 7-2.986zM12 12a2 2 0 100-4 2 2 0 000 4z"></path></svg>
                        </div>
                        <div class="text-left">
                            <h3 class="font-bold text-gray-900">Resident App</h3>
                            <p class="text-xs text-gray-500">For Homeowners & Users</p>
                        </div>
                        <svg class="w-5 h-5 ml-auto text-gray-300 group-hover:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>

                    <a href="?mode=officer" class="group relative flex items-center p-4 bg-white border-2 border-blue-100 rounded-xl shadow-sm hover:border-blue-500 hover:shadow-md transition-all">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 mr-4 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        </div>
                        <div class="text-left">
                            <h3 class="font-bold text-gray-900">Officer Hub</h3>
                            <p class="text-xs text-gray-500">For BFP Personnel</p>
                        </div>
                        <svg class="w-5 h-5 ml-auto text-gray-300 group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </a>
                </div>
            </div>

        <?php else: ?>
            <div class="space-y-4">
                <a href="install.php" class="absolute top-6 left-6 text-gray-500 hover:text-gray-800 flex items-center gap-1 text-sm font-semibold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg> Back
                </a>

                <div class="w-24 h-24 <?php echo $mode === 'officer' ? 'bg-blue-600' : 'bg-red-700'; ?> rounded-2xl mx-auto flex items-center justify-center shadow-xl mb-6">
                    <img src="<?php echo $icon; ?>" class="w-14 h-14 object-contain filter brightness-0 invert">
                </div>
                
                <h1 class="text-3xl font-bold <?php echo $mode === 'officer' ? 'text-white' : 'text-gray-900'; ?>"><?php echo $title; ?></h1>
                <p class="<?php echo $mode === 'officer' ? 'text-gray-400' : 'text-gray-500'; ?>"><?php echo $subtitle; ?></p>
                
                <div id="installContainer" class="hidden pt-6">
                    <button id="installBtn" class="w-full <?php echo $btnColor; ?> text-white font-bold py-4 px-8 rounded-xl shadow-lg flex items-center justify-center gap-3 transition-transform active:scale-95">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span id="installBtnText">Install App Now</span>
                    </button>
                    <p class="text-xs <?php echo $mode === 'officer' ? 'text-gray-500' : 'text-gray-400'; ?> mt-4">Takes less than 5MB • Works Offline</p>
                </div>

                <div id="iosInstructions" class="hidden bg-white p-6 rounded-2xl shadow-sm border border-gray-200 text-left mt-6">
                    <h3 class="font-bold text-gray-800 mb-3 text-center">To Install on iOS:</h3>
                    <ol class="space-y-3 text-sm text-gray-600 list-decimal pl-4">
                        <li>Tap the <strong>Share</strong> button at the bottom.</li>
                        <li>Scroll down and select <strong>"Add to Home Screen"</strong>.</li>
                        <li>Tap <strong>Add</strong>.</li>
                    </ol>
                </div>
                
                <div id="compatibilityError" class="hidden bg-yellow-50 p-4 rounded-xl border border-yellow-200 text-left mt-6">
                    <h3 class="font-bold text-yellow-800 mb-2">Installation Tips:</h3>
                    <ul class="text-sm text-yellow-700 space-y-1 list-disc pl-4">
                        <li>Make sure you're using HTTPS (not HTTP)</li>
                        <li>Try a different browser (Chrome, Edge, Samsung Internet)</li>
                        <li>Disable private/incognito mode</li>
                        <li>Check if app is already installed</li>
                    </ul>
                </div>
                
                <div class="pt-8">
                     <a href="<?php echo $mode === 'officer' ? 'bfplogin.php' : 'index.php'; ?>" class="<?php echo $mode === 'officer' ? 'text-blue-400 hover:text-blue-300' : 'text-red-600 hover:text-red-700'; ?> text-sm font-semibold hover:underline block">
                        Open in Browser instead →
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>

  <script>
    // 1. Service Worker Registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('service-worker.js')
                .then(reg => {
                    console.log('✅ Service Worker Registered');
                    // Check for updates periodically
                    setInterval(() => reg.update(), 60000);
                })
                .catch(err => console.error('❌ Service Worker Failed:', err));
        });
    }

    let deferredPrompt;
    const installContainer = document.getElementById('installContainer');
    const installBtn = document.getElementById('installBtn');
    const installBtnText = document.getElementById('installBtnText');
    const iosInstructions = document.getElementById('iosInstructions');
    const compatibilityError = document.getElementById('compatibilityError');
    
    // Detect Platform
    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const isAndroid = /Android/.test(navigator.userAgent);
    const isInStandaloneMode = (window.matchMedia('(display-mode: standalone)').matches) || (window.navigator.standalone === true);

    console.log('Device Info:', { isIos, isAndroid, isInStandaloneMode });

    // LOGIC: Show appropriate UI
    if (isIos) {
        if(iosInstructions) iosInstructions.classList.remove('hidden');
    } 
    else if (isInStandaloneMode) {
        if(installContainer) installContainer.innerHTML = '<p class="text-green-600 font-bold p-4">✅ App is installed and running.</p>';
        if(installContainer) installContainer.classList.remove('hidden');
    }
    else {
        // Listen for the browser prompt (Android, Windows, Mac)
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('✅ beforeinstallprompt fired - Ready to install!');
            e.preventDefault();
            deferredPrompt = e;
            if(installContainer) installContainer.classList.remove('hidden');
            if(installBtn) installBtn.style.opacity = '1';
        });
        
        // FAILSAFE: Show button after timeout with options
        setTimeout(() => {
            if (installContainer && installContainer.classList.contains('hidden')) {
                console.log('⚠️ beforeinstallprompt did not fire');
                if(installContainer) installContainer.classList.remove('hidden');
                if(compatibilityError) compatibilityError.classList.remove('hidden');
                if(installBtn) {
                   installBtn.style.opacity = '0.6';
                   installBtnText.textContent = 'Installation Not Available';
                   installBtn.disabled = true;
                }
            }
        }, 3000);
    }

    // Button Click Handler
    if (installBtn) {
        installBtn.addEventListener('click', async () => {
            if (!deferredPrompt) {
                if(compatibilityError) compatibilityError.classList.remove('hidden');
                return;
            }
            
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`User response: ${outcome}`);
            
            if (outcome === 'accepted') {
                installBtnText.textContent = '✅ Installation Complete!';
                installBtn.disabled = true;
            }
            
            deferredPrompt = null;
        });
    }

    // Request notification permission for PWA features
    if (Notification.permission === 'default') {
        Notification.requestPermission();
    }
  </script>
</body>
</html>