<?php
/**
 * Intelligent Matching Engine
 * Weighted Scoring Algorithm
 * 
 * Weights:
 * - Category: 30%
 * - Colour: 20%
 * - Location: 20%
 * - Date proximity: 15%
 * - Keyword similarity: 15%
 * 
 * Threshold: 70%
 */

function calculateMatchScore($item1, $item2) {
    $score = 0;

    // 1. Category match (30%)
    if (strcasecmp(trim($item1['category']), trim($item2['category'])) === 0) {
        $score += 30;
    }

    // 2. Colour match (20%) - partial match allowed
    if (!empty($item1['colour']) && !empty($item2['colour'])) {
        if (strcasecmp(trim($item1['colour']), trim($item2['colour'])) === 0) {
            $score += 20;
        } else {
            // Partial match using similar_text
            $percent = 0.0;
            similar_text(strtolower($item1['colour']), strtolower($item2['colour']), $percent);
            if ($percent >= 60) {
                $score += 10;
            }
        }
    }

    // 3. Location match (20%)
    if (!empty($item1['location']) && !empty($item2['location'])) {
        if (strcasecmp(trim($item1['location']), trim($item2['location'])) === 0) {
            $score += 20;
        } else {
            $percent = 0.0;
            similar_text(strtolower($item1['location']), strtolower($item2['location']), $percent);
            if ($percent >= 60) {
                $score += 10;
            }
        }
    }

    // 4. Date proximity (15%)
    if (!empty($item1['date_lost_found']) && !empty($item2['date_lost_found'])) {
        $d1 = strtotime($item1['date_lost_found']);
        $d2 = strtotime($item2['date_lost_found']);
        $days_diff = abs($d1 - $d2) / 86400;

        if ($days_diff <= 3) {
            $score += 15;
        } elseif ($days_diff <= 7) {
            $score += 10;
        } elseif ($days_diff <= 14) {
            $score += 5;
        }
    }

    // 5. Keyword similarity (15%) - compare name + description
    $text1 = strtolower($item1['item_name'] . ' ' . $item1['description']);
    $text2 = strtolower($item2['item_name'] . ' ' . $item2['description']);

    // Extract words > 3 chars
    $words1 = array_filter(explode(' ', preg_replace('/[^a-z0-9 ]/', ' ', $text1)), fn($w) => strlen($w) > 3);
    $words2 = array_filter(explode(' ', preg_replace('/[^a-z0-9 ]/', ' ', $text2)), fn($w) => strlen($w) > 3);

    if (!empty($words1) && !empty($words2)) {
        $common = array_intersect($words1, $words2);
        $total_unique = count(array_unique(array_merge($words1, $words2)));
        if ($total_unique > 0) {
            $keyword_score = (count($common) / $total_unique) * 15;
            $score += $keyword_score;
        }
    }

    return round($score, 2);
}

function runMatchingEngine($conn, $new_item_id) {
    // Fetch the new item
    $stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
    $stmt->bind_param("i", $new_item_id);
    $stmt->execute();
    $new_item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$new_item) return [];

    // Determine opposite type
    $opposite = ($new_item['type'] === 'lost') ? 'found' : 'lost';

    // Fetch all opposite-type items (exclude same user's own items)
    $stmt = $conn->prepare("SELECT * FROM items WHERE type = ? AND item_id != ?");
    $stmt->bind_param("si", $opposite, $new_item_id);
    $stmt->execute();
    $candidates = $stmt->get_result();
    $stmt->close();

    $matches = [];

    while ($candidate = $candidates->fetch_assoc()) {
        $score = calculateMatchScore($new_item, $candidate);

        if ($score >= 70) {
            // Determine which is lost and which is found
            if ($new_item['type'] === 'lost') {
                $lost_id = $new_item['item_id'];
                $found_id = $candidate['item_id'];
            } else {
                $lost_id = $candidate['item_id'];
                $found_id = $new_item['item_id'];
            }

            // Check if match already exists
            $check = $conn->prepare("SELECT match_id FROM match_results WHERE lost_item_id = ? AND found_item_id = ?");
            $check->bind_param("ii", $lost_id, $found_id);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$exists) {
                $insert = $conn->prepare("INSERT INTO match_results (lost_item_id, found_item_id, match_score, match_status) VALUES (?, ?, ?, 'suggested')");
                $insert->bind_param("iid", $lost_id, $found_id, $score);
                $insert->execute();
                $insert->close();
            }

            $matches[] = [
                'item' => $candidate,
                'score' => $score
            ];
        }
    }

    return $matches;
}
?>