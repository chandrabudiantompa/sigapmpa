<?php
declare(strict_types=1);

function recruitment_score(string $type, array $answers): array
{
    $keys = require __DIR__ . '/recruitment_keys.php';
    if (!in_array($type, ['aptitude', 'workstyle'], true)) {
        throw new InvalidArgumentException('Jenis tes tidak valid.');
    }
    $total = $type === 'aptitude' ? 60 : 24;
    foreach ($answers as $number => $answer) {
        if (!ctype_digit((string)$number) || (string)(int)$number !== (string)$number || (int)$number < 1 || (int)$number > $total) {
            throw new InvalidArgumentException('Nomor soal tidak valid.');
        }
    }
    if ($type === 'aptitude') {
        $correct = 0;
        $incorrect = 0;
        for ($number = 1; $number <= $total; $number++) {
            $answer = $answers[$number] ?? null;
            if ($answer === null || $answer === '') continue;
            if (!is_string($answer) || !in_array($answer, $keys['allowed'][$number], true)) {
                throw new InvalidArgumentException("Pilihan pada soal $number tidak valid.");
            }
            if ($answer === $keys['aptitude'][$number]) $correct++;
            else $incorrect++;
        }
        return ['test' => $type, 'key_version' => $keys['key_version'], 'correct' => $correct,
            'incorrect' => $incorrect, 'unanswered' => $total - $correct - $incorrect,
            'total' => $total, 'percentage' => round($correct / $total * 100, 2)];
    }
    $most = $least = array_fill_keys(['D', 'I', 'S', 'C'], 0);
    for ($number = 1; $number <= $total; $number++) {
        $answer = $answers[$number] ?? null;
        if (!is_array($answer) || count($answer) !== 2 || !is_string($answer['most'] ?? null) || !is_string($answer['least'] ?? null)
            || !isset($keys['workstyle'][$number][$answer['most']], $keys['workstyle'][$number][$answer['least']])
            || $answer['most'] === $answer['least']) {
            throw new InvalidArgumentException("Lengkapi grup $number dengan dua pilihan yang berbeda.");
        }
        $most[$keys['workstyle'][$number][$answer['most']]]++;
        $least[$keys['workstyle'][$number][$answer['least']]]++;
    }
    $net = [];
    foreach ($most as $dimension => $value) $net[$dimension] = $value - $least[$dimension];
    $ranking = array_keys($net);
    usort($ranking, static fn($a, $b) => $net[$b] <=> $net[$a]);
    $dominant = array_values(array_filter($ranking, static fn($dimension) => $net[$dimension] === max($net)));
    return ['test' => $type, 'matrix_version' => $keys['matrix_version'], 'most' => $most, 'least' => $least,
        'net' => $net, 'ranking' => $ranking, 'dominant' => $dominant, 'total' => $total, 'status' => 'needs_hr_review'];
}
