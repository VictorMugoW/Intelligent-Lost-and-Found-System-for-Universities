<?php
/**
 * Intelligent Matching Engine
 * Weighted Scoring Algorithm with Detailed Breakdown
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

function getMatchBreakdown($item1, $item2) {
    $breakdown = [
        'category' => ['score' => 0, 'max' => 30, 'label' => 'Category', 'note' => 'No match', 'matched' => false],
        'colour'   => ['score' => 0, 'max' => 20, 'label' => 'Colour', 'note' => 'No match', 'matched' => false],
        'location' => ['score' => 0, 'max' => 20, 'label' => 'Location', 'note' => 'No match', 'matched' => false],
        'date'     => ['score' => 0, 'max' => 15, 'label' => 'Date Proximity', 'note' => 'No match', 'matched' => false],
        'keywords' => ['score' => 0, 'max' => 15, 'label' => 'Keyword Similarity', 'note' => 'No match', 'matched' => false],
    ];

    // 1. Category (30%)
    if (strcasecmp(trim($item1['category']), trim($item2['category'])) === 0) {
        $breakdown['category']['score'] = 30;
        $breakdown['category']['note'] = "'" . $item1['category'] . "' matches '" . $item2['category'] . "'";
        $breakdown['category']['matched'] = true;
    } else {
        $breakdown['category']['note'] = "'" . $item1['category'] . "' vs '" . $item2['category'] . "'";
    }

    // 2. Colour (20%)
    if (!empty($item1['colour']) && !empty($item2['colour'])) {
        if (strcasecmp(trim($item1['colour']), trim($item2['colour'])) === 0) {
            $breakdown['colour']['score'] = 20;
            $breakdown['colour']['note'] = "'" . $item1['colour'] . "' matches exactly";
            $breakdown['colour']['matched'] = true;
        } else {
            $percent = 0.0;
            similar_text(strtolower($item1['colour']), strtolower($item2['colour']), $percent);
            if ($percent >= 60) {
                $breakdown['colour']['score'] = 10;
                $breakdown['colour']['note'] = "Similar (" . round($percent) . "%) - '" . $item1['colour'] . "' vs '" . $item2['colour'] . "'";
                $breakdown['colour']['matched'] = true;
            } else {
                $breakdown['colour']['note'] = "'" . $item1['colour'] . "' vs '" . $item2['colour'] . "'";
            }
        }
    } else {
        $breakdown['colour']['note'] = 'Colour not provided';
    }

    // 3. Location (20%)
    if (!empty($item1['location']) && !empty($item2['location'])) {
        if (strcasecmp(trim($item1['location']), trim($item2['location'])) === 0) {
            $breakdown['location']['score'] = 20;
            $breakdown['location']['note'] = "'" . $item1['location'] . "' matches exactly";
            $breakdown['location']['matched'] = true;
        } else {
            $percent = 0.0;
            similar_text(strtolower($item1['location']), strtolower($item2['location']), $percent);
            if ($percent >= 60) {
                $breakdown['location']['score'] = 10;
                $breakdown['location']['note'] = "Similar (" . round($percent) . "%) - '" . $item1['location'] . "' vs '" . $item2['location'] . "'";
                $breakdown['location']['matched'] = true;
            } else {
                $breakdown['location']['note'] = "'" . $item1['location'] . "' vs '" . $item2['location'] . "'";
            }
        }
    } else {
        $breakdown['location']['note'] = 'Location not provided';
    }

    // 4. Date proximity (15%)
    if (!empty($item1['date_lost_found']) && !empty($item2['date_lost_found'])) {
        $d1 = strtotime($item1['date_lost_found']);
        $d2 = strtotime($item2['date_lost_found']);
        $days_diff = abs($d1 - $d2) / 86400;
        $days_rounded = round($days_diff);

        if ($days_diff <= 3) {
            $breakdown['date']['score'] = 15;
            $breakdown['date']['note'] = $days_rounded . " days apart (very close)";
            $breakdown['date']['matched'] = true;
        } elseif ($days_diff <= 7) {
            $breakdown['date']['score'] = 10;
            $breakdown['date']['note'] = $days_rounded . " days apart (close)";
            $breakdown['date']['matched'] = true;
        } elseif ($days_diff <= 14) {
            $breakdown['date']['score'] = 5;
            $breakdown['date']['note'] = $days_rounded . " days apart (moderate)";
            $breakdown['date']['matched'] = true;
        } else {
            $breakdown['date']['note'] = $days_rounded . " days apart (too far)";
        }
    } else {
        $breakdown['date']['note'] = 'Date not provided';
    }

    // 5. Keyword similarity (15%) - FIXED: uses unique words and caps at max
    $text1 = strtolower($item1['item_name'] . ' ' . $item1['description']);
    $text2 = strtolower($item2['item_name'] . ' ' . $item2['description']);

    $words1 = array_filter(explode(' ', preg_replace('/[^a-z0-9 ]/', ' ', $text1)), fn($w) => strlen($w) > 3);
    $words2 = array_filter(explode(' ', preg_replace('/[^a-z0-9 ]/', ' ', $text2)), fn($w) => strlen($w) > 3);

    if (!empty($words1) && !empty($words2)) {
        $words1_unique = array_values(array_unique($words1));
        $words2_unique = array_values(array_unique($words2));
        $common = array_values(array_intersect($words1_unique, $words2_unique));
        $total_unique = count(array_unique(array_merge($words1_unique, $words2_unique)));

        if ($total_unique > 0) {
            $keyword_score = (count($common) / $total_unique) * 15;
            // Cap at maximum (15) to prevent exceeding 100% total
            $keyword_score = min($keyword_score, 15);
            $breakdown['keywords']['score'] = round($keyword_score, 1);

            $common_list = array_slice($common, 0, 3);
            if (count($common) > 0) {
                $breakdown['keywords']['note'] = count($common) . " shared word(s): " . implode(', ', $common_list);
                $breakdown['keywords']['matched'] = true;
            } else {
                $breakdown['keywords']['note'] = 'No shared keywords';
            }
        }
    } else {
        $breakdown['keywords']['note'] = 'Not enough text to compare';
    }

    // Total (capped at 100)
    $total = 0;
    foreach ($breakdown as $key => $data) {
        if ($key === 'total') continue;
        $total += $data['score'];
    }
    $breakdown['total'] = min(round($total, 1), 100);

    return $breakdown;
}

function calculateMatchScore($item1, $item2) {
    $breakdown = getMatchBreakdown($item1, $item2);
    return $breakdown['total'];
}

function runMatchingEngine($conn, $new_item_id) {
    $stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
    $stmt->bind_param("i", $new_item_id);
    $stmt->execute();
    $new_item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$new_item) return [];

    $opposite = ($new_item['type'] === 'lost') ? 'found' : 'lost';

    $stmt = $conn->prepare("SELECT * FROM items WHERE type = ? AND item_id != ?");
    $stmt->bind_param("si", $opposite, $new_item_id);
    $stmt->execute();
    $candidates = $stmt->get_result();
    $stmt->close();

    $matches = [];

    while ($candidate = $candidates->fetch_assoc()) {
        $score = calculateMatchScore($new_item, $candidate);

        if ($score >= 70) {
            if ($new_item['type'] === 'lost') {
                $lost_id = $new_item['item_id'];
                $found_id = $candidate['item_id'];
            } else {
                $lost_id = $candidate['item_id'];
                $found_id = $new_item['item_id'];
            }

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