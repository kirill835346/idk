<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow requests from Roblox

// Helper function to make HTTP requests to Roblox API
function robloxApiRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    }
    error_log("Error fetching $url: HTTP $httpCode, Response: $response");
    return null;
}

// Handle different API endpoints
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

switch ($endpoint) {
    case 'user-info':
        $username = isset($_GET['username']) ? $_GET['username'] : '';
        if (!$username) {
            echo json_encode(['error' => 'Username is required']);
            exit;
        }
        $response = robloxApiRequest('https://users.roblox.com/v1/usernames/users', 'POST', [
            'usernames' => [$username],
            'excludeBannedUsers' => true
        ]);
        if ($response && isset($response['data'][0])) {
            $user = $response['data'][0];
            echo json_encode([
                'userId' => $user['id'],
                'displayName' => $user['displayName'] ?? $username,
                'username' => $user['name']
            ]);
        } else {
            echo json_encode(['error' => 'User not found']);
        }
        break;

    case 'favorite-games':
        $userId = isset($_GET['userId']) ? $_GET['userId'] : '';
        if (!$userId) {
            echo json_encode(['error' => 'UserId is required']);
            exit;
        }
        $games = [];
        $cursor = '';
        $url = "https://games.roblox.com/v2/users/$userId/favorite/games?accessFilter=2";
        while (true) {
            $response = robloxApiRequest("$url&cursor=$cursor");
            if ($response && isset($response['data'])) {
                foreach ($response['data'] as $game) {
                    if (isset($game['universeId'])) {
                        $games[] = $game['universeId'];
                    }
                }
                $cursor = $response['nextPageCursor'] ?? '';
                if (!$cursor) break;
            } else {
                break;
            }
            sleep(1); // Avoid rate limits
        }
        echo json_encode(['games' => array_unique($games)]);
        break;

    case 'badge-games':
        $userId = isset($_GET['userId']) ? $_GET['userId'] : '';
        if (!$userId) {
            echo json_encode(['error' => 'UserId is required']);
            exit;
        }
        $games = [];
        $cursor = '';
        $url = "https://badges.roblox.com/v1/users/$userId/badges?sortOrder=Desc&limit=100";
        while (true) {
            $response = robloxApiRequest("$url&cursor=$cursor");
            if ($response && isset($response['data'])) {
                foreach ($response['data'] as $badge) {
                    if (isset($badge['awardingUniverseId'])) {
                        $games[] = $badge['awardingUniverseId'];
                    }
                }
                $cursor = $response['nextPageCursor'] ?? '';
                if (!$cursor) break;
            } else {
                break;
            }
            sleep(1);
        }
        echo json_encode(['games' => array_unique($games)]);
        break;

    case 'game-pass-games':
        $userId = isset($_GET['userId']) ? $_GET['userId'] : '';
        if (!$userId) {
            echo json_encode(['error' => 'UserId is required']);
            exit;
        }
        $url = "https://inventory.roblox.com/v2/users/$userId/inventory?assetTypes=GamePass";
        $response = robloxApiRequest($url);
        $games = [];
        if ($response && isset($response['data'])) {
            foreach ($response['data'] as $item) {
                if (isset($item['universeId'])) {
                    $games[] = $item['universeId'];
                }
            }
        }
        echo json_encode(['games' => array_unique($games)]);
        break;

    case 'created-games':
        $userId = isset($_GET['userId']) ? $_GET['userId'] : '';
        if (!$userId) {
            echo json_encode(['error' => 'UserId is required']);
            exit;
        }
        $games = [];
        $cursor = '';
        $url = "https://games.roblox.com/v2/users/$userId/games?accessFilter=Public";
        while (true) {
            $response = robloxApiRequest("$url&cursor=$cursor");
            if ($response && isset($response['data'])) {
                foreach ($response['data'] as $game) {
                    if (isset($game['rootPlace']['universeId'])) {
                        $games[] = $game['rootPlace']['universeId'];
                    }
                }
                $cursor = $response['nextPageCursor'] ?? '';
                if (!$cursor) break;
            } else {
                break;
            }
            sleep(1);
        }
        echo json_encode(['games' => array_unique($games)]);
        break;

    case 'popular-games':
        $url = 'https://games.roblox.com/v1/games/recommendations/algorithm?model.maxRows=10';
        $response = robloxApiRequest($url);
        $games = [];
        if ($response && isset($response['games'])) {
            foreach ($response['games'] as $game) {
                if (isset($game['universeId'])) {
                    $games[] = $game['universeId'];
                }
            }
        }
        echo json_encode(['games' => array_unique($games)]);
        break;

    case 'search-player':
        $userId = isset($_GET['userId']) ? $_GET['userId'] : '';
        $universeIds = isset($_GET['universeIds']) ? explode(',', $_GET['universeIds']) : [];
        if (!$userId || !$universeIds) {
            echo json_encode(['error' => 'UserId and universeIds are required']);
            exit;
        }
        foreach ($universeIds as $universeId) {
            $gameResponse = robloxApiRequest("https://games.roblox.com/v1/games?universeIds=$universeId");
            $placeId = $gameResponse['data'][0]['rootPlaceId'] ?? null;
            if (!$placeId) continue;

            $cursor = '';
            $url = "https://games.roblox.com/v1/games/$placeId/servers/Public?sortOrder=Asc";
            while (true) {
                $response = robloxApiRequest("$url&cursor=$cursor");
                if ($response && isset($response['data'])) {
                    foreach ($response['data'] as $server) {
                        if (isset($server['playerIds']) && in_array((int)$userId, $server['playerIds'])) {
                            echo json_encode(['placeId' => $placeId, 'serverId' => $server['id']]);
                            exit;
                        }
                    }
                    $cursor = $response['nextPageCursor'] ?? '';
                    if (!$cursor) break;
                } else {
                    break;
                }
                sleep(1);
            }
        }
        echo json_encode(['error' => 'Player not found in servers']);
        break;

    default:
        echo json_encode(['error' => 'Invalid endpoint']);
        break;
}
?>
