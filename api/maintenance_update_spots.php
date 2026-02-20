<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php';

// Mapping of spot names to image paths (assumes files exist in /laguna-photos/)
$images = [
    'Caliraya Resort Club' => 'laguna-photos/Caliraya Resort Club.jpg',
    'SM City Calamba' => 'laguna-photos/SM City Calamba.jpg',
    'UPLB' => 'laguna-photos/UPLB.jpg',
    'University of the Philippines Los Baños' => 'laguna-photos/UPLB.jpg'
];

$results = [
    'updated' => [],
    'deleted' => [],
    'errors' => []
];

try {
    foreach ($images as $name => $path) {
        $stmt = $conn->prepare("UPDATE spots SET image = ? WHERE name = ? AND (image IS NULL OR image = '' OR image = 'laguna-photos/default.jpg')");
        $stmt->bind_param("ss", $path, $name);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $results['updated'][] = ['name' => $name, 'image' => $path];
            }
        } else {
            $results['errors'][] = "Failed to update image for $name: " . $conn->error;
        }
        $stmt->close();
    }

    // Force-set UPLB image even if an image already exists
    $forceNames = [
        'UPLB',
        'University of the Philippines Los Baños',
        'University of the Philippines Los Banos'
    ];
    foreach ($forceNames as $fname) {
        $path = 'laguna-photos/UPLB.jpg';
        $stmt = $conn->prepare("UPDATE spots SET image = ? WHERE name = ?");
        $stmt->bind_param("ss", $path, $fname);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $results['updated'][] = ['name' => $fname, 'image' => $path, 'forced' => true];
            }
        } else {
            $results['errors'][] = "Failed to force update image for $fname: " . $conn->error;
        }
        $stmt->close();
    }

    // Broad match for UPLB variants
    $path = 'laguna-photos/UPLB.jpg';
    $sql = "UPDATE spots SET image = ? WHERE (name LIKE '%UPLB%' OR name LIKE '%University of the Philippines%')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $path);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $results['updated'][] = ['name_like' => 'UPLB|University of the Philippines', 'image' => $path, 'forced' => true];
        }
    } else {
        $results['errors'][] = "Failed broad UPLB update: " . $conn->error;
    }
    $stmt->close();

    // Delete Sulyap entry (handle accented and unaccented variants)
    $deleteNames = [
        'Sulyap Gallery Café & Boutique Hotel',
        'Sulyap Gallery Cafe & Boutique Hotel',
        'Sulyap Gallery Café',
        'Sulyap Gallery Cafe'
    ];
    foreach ($deleteNames as $delName) {
        $stmt = $conn->prepare("DELETE FROM spots WHERE name = ?");
        $stmt->bind_param("s", $delName);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $results['deleted'][] = $delName;
            }
        } else {
            $results['errors'][] = "Failed to delete $delName: " . $conn->error;
        }
        $stmt->close();
    }

    echo json_encode(['success' => true, 'results' => $results]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
