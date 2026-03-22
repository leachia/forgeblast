<?php
header('Content-Type: application/json');
require_once 'config.php';

// Check for session if needed, but for now we'll allow public mock data if requested
// if (!isset($_SESSION['user_id'])) { http_response_code(401); die(json_encode(["error" => "Unauthorized"])); }

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getStatus':
        echo json_encode([
            "status" => "patrolling",
            "battery" => rand(60, 95),
            "altitude" => 12.4,
            "speed" => 2.1,
            "chamber_status" => "sealed",
            "neutralized_count" => 47
        ]);
        break;

    case 'getSensors':
        echo json_encode([
            "temperature" => 24 + (rand(0, 50) / 10),
            "humidity" => 60 + rand(0, 10),
            "co2" => 400 + rand(0, 50),
            "gps" => ["lat" => 14.5995, "lng" => 120.9842]
        ]);
        break;

    case 'getHeatmap':
        // Generate random hotspots in Manila (around 14.5995, 120.9842)
        $hotspots = [];
        for ($i = 0; $i < 10; $i++) {
            $hotspots[] = [
                "lat" => 14.59 + (rand(0, 200) / 10000),
                "lng" => 120.98 + (rand(0, 200) / 10000),
                "intensity" => rand(1, 10) / 10
            ];
        }
        echo json_encode($hotspots);
        break;

    case 'command':
        $cmd = $_POST['cmd'] ?? '';
        // In a real scenario, this would send an MQTT/WebSocket command to the drone
        error_log("Drone Command Received: " . $cmd);
        echo json_encode(["status" => "success", "message" => "Command '$cmd' relayed to drone."]);
        break;

    case 'getLogs':
        echo json_encode([
            ["msg" => "SYSTEM_INIT: SUCCESS", "time" => date('H:i:s')],
            ["msg" => "LINK_STABLISHED: AEROGUARD082", "time" => date('H:i:s')],
            ["msg" => "PATROL_STARTED: QUADRANT_7", "time" => date('H:i:s')]
        ]);
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        break;
}
