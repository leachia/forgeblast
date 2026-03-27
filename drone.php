<?php
require_once 'config.php';
// We'll use the existing config.php for database connection
// but we'll try to use a different database or prefix tables.
$appName = "AeroGuard AI";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $appName; ?> | Mosquito Defense System</title>
    <!-- Use Google Fonts (Inter & Outfit as requested) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Outfit:wght@700;900&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <!-- Use Leaflet for GPS-based Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-glow: rgba(59, 130, 246, 0.5);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.5);
            --bg: #030712;
            --card-bg: rgba(17, 24, 39, 0.7);
            --border: rgba(255, 255, 255, 0.1);
            --text-main: #f3f4f6;
            --text-dim: #9ca3af;
            --success: #10b981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text-main); overflow-x: hidden; min-height: 100vh; }

        /* Smooth Mesh Background */
        .mesh-bg {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
            background: 
                radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(239, 68, 68, 0.1) 0%, transparent 40%);
        }

        .dashboard-container { display: grid; grid-template-columns: 300px 1fr 340px; height: 100vh; gap: 1.5rem; padding: 1.5rem; }

        /* Left Side: Navigation & Quick Control */
        .sidebar { background: var(--card-bg); backdrop-filter: blur(20px); border: 1px solid var(--border); border-radius: 24px; padding: 2rem; display: flex; flex-direction: column; }
        .logo { font-family: 'Outfit', sans-serif; font-size: 1.8rem; margin-bottom: 3rem; display: flex; align-items: center; gap: 0.8rem; }
        .logo ion-icon { color: var(--primary); font-size: 2.2rem; }
        .nav-link { 
            display: flex; align-items: center; gap: 1rem; padding: 1rem; border-radius: 12px; color: var(--text-dim); text-decoration: none; transition: 0.3s; margin-bottom: 0.5rem;
        }
        .nav-link:hover, .nav-link.active { background: rgba(255, 255, 255, 0.05); color: #fff; }
        .nav-link ion-icon { font-size: 1.3rem; }

        /* Center Content: Feed & Map */
        .main-content { display: flex; flex-direction: column; gap: 1.5rem; }
        .live-feed { 
            flex: 1; position: relative; background: #000; border-radius: 24px; overflow: hidden; border: 1px solid var(--border);
            box-shadow: 0 0 40px rgba(0,0,0,0.5);
        }
        .feed-overlay { 
            position: absolute; inset: 0; padding: 1.5rem; pointer-events: none;
            display: flex; flex-direction: column; justify-content: space-between;
        }
        .bounding-box {
            position: absolute; border: 2px solid var(--primary); background: rgba(59, 130, 246, 0.1); border-radius: 4px;
            animation: pulse-border 1.5s infinite;
        }
        @keyframes pulse-border { 0% { border-color: var(--primary); } 50% { border-color: #fff; } 100% { border-color: var(--primary); } }

        .map-section { height: 350px; background: var(--card-bg); backdrop-filter: blur(20px); border: 1px solid var(--border); border-radius: 24px; overflow: hidden; }

        /* Right Side: Telemetry & Status */
        .telemetry-panel { background: var(--card-bg); backdrop-filter: blur(20px); border: 1px solid var(--border); border-radius: 24px; padding: 2rem; display: flex; flex-direction: column; gap: 1rem; }
        .stat-card-mini { background: rgba(255,255,255,0.03); border: 1px solid var(--border); padding: 1rem; border-radius: 16px; display: flex; align-items: center; gap: 1rem; }
        .stat-card-mini ion-icon { font-size: 1.5rem; padding: 0.6rem; border-radius: 12px; }
        .icon-blue { background: rgba(59, 130, 246, 0.1); color: var(--primary); }
        .icon-red { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .icon-green { background: rgba(16, 185, 129, 0.1); color: var(--success); }

        .btn-control {
            width: 100%; padding: 1.2rem; border-radius: 16px; border: none; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.8rem; transition: 0.3s;
        }
        .btn-start { background: var(--primary); color: #fff; box-shadow: 0 10px 20px var(--primary-glow); }
        .btn-stop { background: var(--danger); color: #fff; box-shadow: 0 10px 20px var(--danger-glow); }
        .btn-control:hover { transform: translateY(-3px); }

        #map { height: 100%; width: 100%; opacity: 1; filter: invert(90%) hue-rotate(180deg) brightness(1.2) contrast(0.8); }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }
    </style>
</head>
<body>
    <div class="mesh-bg"></div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <ion-icon name="shield-checkmark"></ion-icon>
                <span>AeroGuard</span>
            </div>
            <nav style="flex: 1;">
                <a href="#" class="nav-link active"><ion-icon name="grid-outline"></ion-icon> Overview</a>
                <a href="#" class="nav-link"><ion-icon name="map-outline"></ion-icon> Patrol Map</a>
                <a href="#" class="nav-link"><ion-icon name="stats-chart-outline"></ion-icon> Analytics</a>
                <a href="#" class="nav-link"><ion-icon name="videocam-outline"></ion-icon> Records</a>
                <a href="#" class="nav-link"><ion-icon name="settings-outline"></ion-icon> Settings</a>
            </nav>
            
            <div style="margin-top: auto;">
                <button class="btn-control btn-start" id="mainActionBtn">
                    <ion-icon name="play-sharp"></ion-icon> START PATROL
                </button>
                <div style="display:flex; justify-content:space-between; margin-top:1.5rem; font-size:0.8rem; color:var(--text-dim);">
                    <span>FIRMWARE V2.5</span>
                    <span>LINK: STABLE</span>
                </div>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="main-content">
            <div class="live-feed">
                <!-- Mock Video Source (High-res Nature/Drone-like view) -->
                <div style="width:100%; height:100%; background: url('https://images.unsplash.com/photo-1593113598332-cd288d649433?auto=format&fit=crop&q=80&w=1200') center/cover; opacity:0.6;"></div>
                
                <div class="feed-overlay">
                    <div style="display:flex; justify-content:space-between; align-items:start;">
                        <span id="liveIndicator" style="background:var(--danger); padding:0.4rem 1rem; border-radius:8px; font-weight:800; font-size:0.8rem; letter-spacing:1px;">REC ● LIVE</span>
                        <div style="text-align:right;">
                            <h2 style="font-family:'Outfit'; font-size:1.5rem;">FLIGHT_MISSION_082</h2>
                            <p style="font-size:0.8rem; color:var(--text-dim);">ALT: 12.4m | SPEED: 2.1 m/s</p>
                        </div>
                    </div>

                    <!-- AI Bounding Boxes Mock -->
                    <div class="bounding-box" style="top: 35%; left: 48%; width: 70px; height: 70px;">
                        <span style="position:absolute; top:-25px; left:0; color:var(--primary); font-size:0.7rem; font-weight:800; white-space:nowrap; background:rgba(0,0,0,0.5); padding:2px 6px; border-radius:4px;">MOSQUITO_AE 98%</span>
                    </div>

                    <div style="display:flex; gap:1.5rem; align-items:flex-end;">
                        <div style="background:rgba(0,0,0,0.5); padding:1rem; border-radius:12px; backdrop-filter:blur(10px); border:1px solid var(--border); flex: 1; max-width: 400px;">
                            <p style="font-size:0.7rem; color:var(--text-dim); margin-bottom:0.4rem; font-weight:800;">DETECTION_LOG</p>
                            <div id="detectionLog" style="font-size:0.7rem; font-family:monospace; line-height:1.4;">
                                <p style="color:var(--text-main);">> TARGET IDENTIFIED AT 14.605, 120.985</p>
                                <p style="color:var(--success); font-weight:800;">> CHAMBER ENGAGED | SUCTION ACTIVE</p>
                            </div>
                        </div>
                        <div style="background:rgba(0,0,0,0.5); padding:1.2rem; border-radius:12px; backdrop-filter:blur(10px); border:1px solid var(--border);">
                            <p style="font-size:0.6rem; color:var(--text-dim); margin-bottom:0.2rem;">SIGNAL</p>
                            <h4 style="color:var(--success);">98%</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="map-section">
                <div id="map"></div>
            </div>
        </main>

        <!-- Rights Side: Data -->
        <aside class="telemetry-panel">
            <h3 style="font-family:'Outfit'; font-size:1.2rem; letter-spacing:-0.5px; margin-bottom:0.5rem;">TELEMETRY</h3>
            
            <div class="stat-card-mini">
                <ion-icon name="thermometer-outline" class="icon-blue"></ion-icon>
                <div>
                    <p style="font-size:0.6rem; color:var(--text-dim);">ENVIRONMENT TEMP</p>
                    <h4 style="font-size:1.2rem;">24.8°C</h4>
                </div>
            </div>

            <div class="stat-card-mini">
                <ion-icon name="water-outline" class="icon-blue"></ion-icon>
                <div>
                    <p style="font-size:0.6rem; color:var(--text-dim);">HUMIDITY</p>
                    <h4 style="font-size:1.2rem;">65%</h4>
                </div>
            </div>

            <div class="stat-card-mini">
                <ion-icon name="cloud-outline" class="icon-green"></ion-icon>
                <div>
                    <p style="font-size:0.6rem; color:var(--text-dim);">CO₂ LEVELS</p>
                    <h4 style="font-size:1.2rem;">412 PPM</h4>
                </div>
            </div>

            <div class="stat-card-mini">
                <ion-icon name="battery-charging-outline" class="icon-green"></ion-icon>
                <div style="flex:1;">
                    <p style="font-size:0.6rem; color:var(--text-dim);">BATTERY STATUS</p>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <h4 style="font-size:1.2rem;">82%</h4>
                        <div style="flex:1; height:4px; background:rgba(255,255,255,0.1); border-radius:2px; overflow:hidden;">
                            <div style="width:82%; height:100%; background:var(--success);"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:auto; padding:1.2rem; background:rgba(239, 68, 68, 0.05); border:1px solid rgba(239, 68, 68, 0.1); border-radius:20px;">
                <h4 style="color:var(--danger); font-size:0.6rem; font-weight:800; margin-bottom:0.8rem; letter-spacing:1px;">CHAMBER LOGISTICS</h4>
                <div style="display:flex; justify-content:space-between; margin-bottom:0.4rem; font-size:0.8rem;">
                    <span style="color:var(--text-dim);">NEUTRALIZED</span>
                    <span style="font-weight:800;">47</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:0.8rem;">
                    <span style="color:var(--text-dim);">CHAMBER STATUS</span>
                    <span style="font-weight:800; color:var(--success);">SEALED</span>
                </div>
            </div>

            <button class="btn-control btn-stop">
                <ion-icon name="power-sharp"></ion-icon> EMERGENCY STOP
            </button>
        </aside>
    </div>

    <script>
        // Initialize Map
        const map = L.map('map', {
            zoomControl: false,
            attributionControl: false
        }).setView([14.5995, 120.9842], 14); 

        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png').addTo(map);

        // Simulation Data
        const hotspots = [
            [14.605, 120.985, 0.8],
            [14.600, 120.980, 0.5],
            [14.610, 120.990, 0.9]
        ];

        // Add circles for hotspots
        hotspots.forEach(hp => {
            L.circle([hp[0], hp[1]], {
                color: hp[2] > 0.7 ? '#ef4444' : '#3b82f6',
                fillColor: hp[2] > 0.7 ? '#ef4444' : '#3b82f6',
                fillOpacity: 0.3,
                radius: 300 * hp[2],
                border: 'none'
            }).addTo(map);
        });

        // REAL-TIME API SYNC
        const updateTelemetry = async () => {
            try {
                const res = await fetch('drone_api.php?action=getSensors');
                const data = await res.json();
                
                // Update Sensors in UI
                document.querySelector('.stat-card-mini:nth-of-type(1) h4').innerText = data.temperature.toFixed(1) + '°C';
                document.querySelector('.stat-card-mini:nth-of-type(2) h4').innerText = data.humidity + '%';
                document.querySelector('.stat-card-mini:nth-of-type(3) h4').innerText = data.co2 + ' PPM';
                
                // Update Heatmap randomly for demo
                const heatmapRes = await fetch('drone_api.php?action=getHeatmap');
                const heatmaps = await heatmapRes.json();
                // Clear existing circles (optional, but keep for simulation)
                // heatmaps.forEach(...) ...
            } catch (e) {
                console.error("Telemetry Sync Error:", e);
            }
        };

        // Command Button Link
        const actionBtn = document.getElementById('mainActionBtn');
        actionBtn.addEventListener('click', async () => {
            const isPatrolling = actionBtn.innerText.includes('STOP');
            const cmd = isPatrolling ? 'STOP_PATROL' : 'START_PATROL';
            
            const formData = new FormData();
            formData.append('cmd', cmd);
            
            const res = await fetch('drone_api.php?action=command', { method: 'POST', body: formData });
            const result = await res.json();
            
            if (result.status === 'success') {
                actionBtn.innerHTML = isPatrolling ? '<ion-icon name="play-sharp"></ion-icon> START PATROL' : '<ion-icon name="stop-sharp"></ion-icon> STOP PATROL';
                actionBtn.classList.toggle('btn-stop');
                actionBtn.classList.toggle('btn-start');
                addDetectionLog(`COMMAND_EXECUTED: ${cmd}`);
            }
        });

        const log = document.getElementById('detectionLog');
        const indicator = document.getElementById('liveIndicator');

        // Pulsing animation for the LIVE indicator
        setInterval(() => {
            indicator.style.opacity = indicator.style.opacity === '0.3' ? '1' : '0.3';
        }, 1000);

        const addDetectionLog = (msg) => {
            const p = document.createElement('p');
            p.style.marginTop = '4px';
            p.innerHTML = `> ${msg}`;
            log.appendChild(p);
            if(log.children.length > 5) log.removeChild(log.firstChild);
        };

        setInterval(updateTelemetry, 5000);
        setInterval(() => {
            if(Math.random() > 0.8) {
                // In a real app, this would be fetched from /getDetections case
                addDetectionLog(`TARGET DETECTED AT ${(14.60 + Math.random()*0.01).toFixed(4)}, ${(120.98 + Math.random()*0.01).toFixed(4)}`);
            }
        }, 3000);
    </script>
</body>
</html>
