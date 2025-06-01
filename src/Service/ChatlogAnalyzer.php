<?php

namespace App\Service;

/**
 * Analyzes Fantasy Grounds chat logs for D&D dice rolls, sessions, and character statistics.
 *
 * Aggregates roll data, detects sessions, and provides detailed statistics for use in analysis views.
 * Tracks skipped/unmatched roll lines for future improvements.
 *
 * @package App\Service
 */
class ChatlogAnalyzer
{
    private array $sessions = [];
    private ?array $currentSession = null;
    private array $debug = [];
    private array $skippedRolls = [];

    /**
     * Analyze a chatlog file and return aggregated analysis data.
     *
     * @param string $filepath Path to the chatlog file
     * @return array Analysis result including totals, sessions, and skipped rolls
     * @throws \RuntimeException If file cannot be read
     */
    public function analyze(string $filepath): array
    {
        $content = file_get_contents($filepath);
        if (!$content) {
            throw new \RuntimeException('Could not read file contents');
        }

        $this->resetState();

        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $this->analyzeLine($line);
        }

        $this->finalizeSession();
        return $this->buildAnalysis();
    }

    /**
     * Reset the analyzer state for a new analysis run.
     *
     * @return void
     */
    private function resetState(): void
    {
        $this->sessions = [];
        $this->currentSession = null;
        $this->debug = [];
        $this->debug[] = "Starting analysis...";
        $this->skippedRolls = [];
    }

    /**
     * Analyze a single line (public for testing).
     *
     * @param string $line
     * @return bool True if the line was handled (session or roll), false otherwise
     */
    public function analyzeLinePublic(string $line): bool
    {
        return $this->analyzeLine($line);
    }

    /**
     * Analyze a single line (internal).
     *
     * @param string $line
     * @return bool True if the line was handled (session or roll), false otherwise
     */
    private function analyzeLine(string $line): bool
    {
        if ($session = $this->detectSessionStart($line)) {
            $this->startNewSession($session['date'], $session['time']);
            $this->debug[] = "Found session start: {$session['date']} at {$session['time']}";
            return true;
        }

        if ($this->isRollLine($line)) {
            $this->debug[] = "Found roll line: " . trim($line);
            $this->processRoll($line);
            return true;
        }
        return false;
    }

    /**
     * Detect if a line starts a new session.
     *
     * @param string $line
     * @return array|null [date, time] if session start, null otherwise
     */
    private function detectSessionStart(string $line): ?array
    {
        if (preg_match('/Session started at (\d{4}-\d{2}-\d{2}) \/ (\d{2}:\d{2})/', $line, $matches) ||
            preg_match('/Chat log started at (\d{1,2}\.\d{1,2}\.\d{4}) \/ (\d{2}:\d{2}:\d{2})/', $line, $matches)) {
            $date = $matches[1];
            $time = $matches[2];
            if (strpos($date, '.') !== false) {
                $dateParts = explode('.', $date);
                $date = sprintf('%04d-%02d-%02d', $dateParts[2], $dateParts[1], $dateParts[0]);
            }
            if (strlen($time) > 5) {
                $time = substr($time, 0, 5);
            }
            return ['date' => $date, 'time' => $time];
        }
        return null;
    }

    /**
     * Determine if a line is a roll line.
     *
     * @param string $line
     * @return bool
     */
    private function isRollLine(string $line): bool
    {
        // Match standard dice notation
        if (preg_match('/\[(?:r?\d+)?d\d+(?:[+\-][^\]=]+)* = \d+\]/', $line)) {
            return true;
        }
        // Match grouped dice notation (g20, 1g20, g20+d20, etc)
        if (preg_match('/\[(?:\d+)?g\d+(?:[+\-][^\]=]+)* = \d+\]/', $line)) {
            return true;
        }
        return false;
    }

    /**
     * Build the aggregated analysis result from parsed sessions.
     *
     * @return array Analysis result
     */
    public function buildAnalysis(): array
    {
        $characterTotals = $this->aggregateCharacterTotals($this->sessions);
        $totalRolls = $this->aggregateTotalRolls($this->sessions);
        $totalValue = $this->aggregateTotalValue($this->sessions);
        $average = $totalRolls > 0 ? round($totalValue / $totalRolls, 2) : 0;
        $this->logFinalSkillTotals($characterTotals);
        $this->debug[] = "Analysis complete. Total rolls: {$totalRolls}, Average: {$average}, Characters: " . count($characterTotals);
        return [
            'totals' => [
                'rolls' => $totalRolls,
                'average' => $average,
                'characters' => $characterTotals
            ],
            'sessions' => $this->sessions,
            'debug' => &$this->debug,
            'skipped_rolls' => $this->skippedRolls
        ];
    }

    /**
     * Aggregate character totals across all sessions.
     *
     * @param array $sessions
     * @return array
     */
    private function aggregateCharacterTotals(array $sessions): array
    {
        $characterTotals = [];
        foreach ($sessions as $session) {
            foreach ($session['characters'] as $charName => $charData) {
                if (!isset($characterTotals[$charName])) {
                    $characterTotals[$charName] = [
                        'rolls' => 0,
                        'total_value' => 0,
                        'average' => 0,
                        'roll_types' => [],
                        'skills' => [],
                        'dice_stats' => [
                            'dice_types' => [],
                            'natural_ones' => 0,
                            'natural_twenties' => 0
                        ]
                    ];
                }
                $characterTotals[$charName]['rolls'] += $charData['rolls'];
                $characterTotals[$charName]['total_value'] += $charData['total_value'];
                $characterTotals[$charName]['average'] =
                    round($characterTotals[$charName]['total_value'] / $characterTotals[$charName]['rolls'], 2);
                foreach ($charData['roll_types'] as $type => $count) {
                    $characterTotals[$charName]['roll_types'][$type] =
                        ($characterTotals[$charName]['roll_types'][$type] ?? 0) + $count;
                }
                foreach ($charData['skills'] as $skill => $count) {
                    $characterTotals[$charName]['skills'][$skill] =
                        ($characterTotals[$charName]['skills'][$skill] ?? 0) + $count;
                }
                $characterTotals[$charName]['dice_stats'] = $this->aggregateDiceStats(
                    $characterTotals[$charName]['dice_stats'],
                    $charData['dice_stats']
                );
            }
        }
        return $characterTotals;
    }

    /**
     * Aggregate dice statistics for a character.
     *
     * @param array $existing
     * @param array $new
     * @return array
     */
    private function aggregateDiceStats(array $existing, array $new): array
    {
        foreach ($new['dice_types'] as $diceType => $diceData) {
            $diceKey = "d{$diceType}";
            if (!isset($existing['dice_types'][$diceKey])) {
                $existing['dice_types'][$diceKey] = [
                    'total_rolls' => 0,
                    'total_value' => 0,
                    'average' => 0,
                    'times_rolled' => 0,
                    'dice_type' => $diceType
                ];
            }
            $diceStats = &$existing['dice_types'][$diceKey];
            $diceStats['times_rolled'] += $diceData['times_rolled'];
            $diceStats['total_rolls'] += $diceData['total_rolls'];
            $diceStats['total_value'] += $diceData['total_value'];
            $diceStats['average'] = $diceStats['times_rolled'] > 0
                ? round($diceStats['total_value'] / $diceStats['times_rolled'], 2)
                : 0;
        }
        $existing['natural_ones'] += $new['natural_ones'];
        $existing['natural_twenties'] += $new['natural_twenties'];
        return $existing;
    }

    /**
     * Aggregate total rolls across all sessions.
     *
     * @param array $sessions
     * @return int
     */
    private function aggregateTotalRolls(array $sessions): int
    {
        $total = 0;
        foreach ($sessions as $session) {
            $total += $session['total_rolls'] ?? 0;
        }
        return $total;
    }

    /**
     * Aggregate total value across all sessions.
     *
     * @param array $sessions
     * @return int
     */
    private function aggregateTotalValue(array $sessions): int
    {
        $total = 0;
        foreach ($sessions as $session) {
            $total += $session['total_value'] ?? 0;
        }
        return $total;
    }

    /**
     * Log final skill totals for debugging.
     *
     * @param array $characterTotals
     * @return void
     */
    private function logFinalSkillTotals(array $characterTotals): void
    {
        foreach ($characterTotals as $charName => $charData) {
            foreach ($charData['skills'] as $skill => $count) {
                $this->debug[] = "Final skill total: {$skill} for character: {$charName} (Total across all sessions: {$count})";
            }
        }
    }

    /**
     * Start a new session, finalizing the previous one if needed.
     *
     * @param string $date
     * @param string $time
     * @return void
     */
    private function startNewSession(string $date, string $time): void
    {
        if ($this->currentSession) {
            $this->finalizeSession();
        }

        $this->currentSession = [
            'date' => $date,
            'time' => $time,
            'total_rolls' => 0,
            'total_value' => 0,
            'characters' => []
        ];
        $this->debug[] = "Started new session: {$date} at {$time}";
    }

    /**
     * Finalize the current session and add it to the sessions list.
     *
     * @return void
     */
    public function finalizeSession(): void
    {
        if (!$this->currentSession) {
            return;
        }

        $this->currentSession['average'] = $this->currentSession['total_rolls'] > 0 
            ? round($this->currentSession['total_value'] / $this->currentSession['total_rolls'], 2) 
            : 0;

        $this->sessions[] = $this->currentSession;
        $this->debug[] = "Finalized session: {$this->currentSession['date']} at {$this->currentSession['time']} - Total rolls: {$this->currentSession['total_rolls']}, Average: {$this->currentSession['average']}";
        
        $this->currentSession = null;
    }

    /**
     * Process a roll line and update session/character stats.
     *
     * @param string $line
     * @return void
     */
    private function processRoll(string $line): void
    {
        if (!$this->currentSession) {
            return;
        }

        $character = $this->extractCharacterName($line);
        if ($character === null) {
            $this->debug[] = "Skipped roll line (no character match): " . trim($line);
            $this->skippedRolls[] = trim($line);
            return;
        }

        $rollData = $this->extractRollData($line);
        if ($rollData === null) {
            $this->debug[] = "Skipped roll line (no roll extraction match): " . trim($line);
            $this->skippedRolls[] = trim($line);
            return;
        }

        $this->initializeCharacterIfNeeded($character, $rollData['diceType']);
        $this->updateSessionAndCharacterStats($character, $rollData);
        $this->handleAdvantageDropped($character, $line);
        $this->extractAndRecordRollTypeAndSkill($character, $line);
    }

    /**
     * Extract the character name from a roll line.
     *
     * @param string $line
     * @return string|null Character name or null if not found
     */
    private function extractCharacterName(string $line): ?string
    {
        if (preg_match('/<font color="#{1,2}[0-9A-Fa-f]{6}">([^:]+):/', $line, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Extract roll data (dice, value, bonus, etc) from a roll line.
     *
     * @param string $line
     * @return array|null Array with keys: numDice, diceType, bonus, totalValue, actualRoll; or null if not matched
     */
    private function extractRollData(string $line): ?array
    {
        // Standard numeric modifier
        if (preg_match('/\[(?:r?(\d+))?d(\d+)(?:([+\-])(\d+))?(?:\+d\d+)?(?:\+\d+)? = (\d+)\]/', $line, $matches)) {
            $numDice = (int)($matches[1] ?? 1);
            $diceType = (int)$matches[2];
            $sign = $matches[3] ?? null;
            $bonusValue = isset($matches[4]) ? (int)$matches[4] : 0;
            $totalValue = (int)$matches[5];
            if ($sign === '-') {
                $actualRoll = $totalValue + $bonusValue;
                $bonus = -$bonusValue;
            } else {
                $actualRoll = $totalValue - $bonusValue;
                $bonus = $bonusValue;
            }
            return compact('numDice', 'diceType', 'bonus', 'totalValue', 'actualRoll');
        }
        // Proficiency bonus modifier
        if (preg_match('/\[(?:r?(\d+))?d(\d+)([+\-])p(\d+)(?:\+d\d+)?(?:\+\d+)? = (\d+)\]/', $line, $matches)) {
            $numDice = (int)($matches[1] ?? 1);
            $diceType = (int)$matches[2];
            $sign = $matches[3];
            $profValue = (int)$matches[4];
            $totalValue = (int)$matches[5];
            if ($sign === '-') {
                $actualRoll = $totalValue + $profValue;
                $bonus = -$profValue;
            } else {
                $actualRoll = $totalValue - $profValue;
                $bonus = $profValue;
            }
            return compact('numDice', 'diceType', 'bonus', 'totalValue', 'actualRoll');
        }
        // Grouped dice
        if (preg_match('/\[(\d+)?g(\d+)(?:[+\-][^\]=]+)* = (\d+)\]/', $line, $matches)) {
            $numDice = (int)($matches[1] ?? 1);
            $diceType = (int)$matches[2];
            $bonus = 0;
            $totalValue = (int)$matches[3];
            $actualRoll = $totalValue;
            return compact('numDice', 'diceType', 'bonus', 'totalValue', 'actualRoll');
        }
        return null;
    }

    /**
     * Initialize character data in the session if not already present.
     *
     * @param string $character
     * @param int $diceType
     * @return void
     */
    private function initializeCharacterIfNeeded(string $character, int $diceType): void
    {
        if (!isset($this->currentSession['characters'][$character])) {
            $this->currentSession['characters'][$character] = [
                'rolls' => 0,
                'total_value' => 0,
                'average' => 0,
                'roll_types' => [],
                'skills' => [],
                'dice_stats' => [
                    'dice_types' => [],
                    'natural_ones' => 0,
                    'natural_twenties' => 0
                ]
            ];
        }
        $diceKey = "d{$diceType}";
        if (!isset($this->currentSession['characters'][$character]['dice_stats']['dice_types'][$diceKey])) {
            $this->currentSession['characters'][$character]['dice_stats']['dice_types'][$diceKey] = [
                'total_rolls' => 0,
                'total_value' => 0,
                'average' => 0,
                'times_rolled' => 0,
                'dice_type' => $diceType
            ];
        }
    }

    /**
     * Update session and character stats for a roll.
     *
     * @param string $character
     * @param array $rollData
     * @return void
     */
    private function updateSessionAndCharacterStats(string $character, array $rollData): void
    {
        extract($rollData);
        // Update session totals
        $this->currentSession['total_rolls']++;
        $this->currentSession['total_value'] += $totalValue;
        // Update character totals
        $this->currentSession['characters'][$character]['rolls']++;
        $this->currentSession['characters'][$character]['total_value'] += $totalValue;
        $this->currentSession['characters'][$character]['average'] =
            round($this->currentSession['characters'][$character]['total_value'] /
                $this->currentSession['characters'][$character]['rolls'], 2);
        // Update dice statistics
        $diceKey = "d{$diceType}";
        $diceStats = &$this->currentSession['characters'][$character]['dice_stats']['dice_types'][$diceKey];
        $diceStats['times_rolled'] += $numDice;
        $diceStats['total_rolls']++;
        $diceStats['total_value'] += $actualRoll;
        $diceStats['average'] = $diceStats['times_rolled'] > 0
            ? round($diceStats['total_value'] / $diceStats['times_rolled'], 2)
            : 0;
        // Track natural ones and twenties for d20
        if ($diceType === 20) {
            if ($actualRoll === 1) {
                $this->currentSession['characters'][$character]['dice_stats']['natural_ones']++;
            } elseif ($actualRoll === 20) {
                $this->currentSession['characters'][$character]['dice_stats']['natural_twenties']++;
            }
        }
    }

    /**
     * Handle [ADV] and [DIS] logic for advantage rolls.
     *
     * @param string $character
     * @param string $line
     * @return void
     */
    private function handleAdvantageDropped(string $character, string $line): void
    {
        if ((strpos($line, '[ADV]') !== false || strpos($line, '[DIS]') !== false) && preg_match('/\[DROPPED (\d+)\]/', $line, $dropMatch)) {
            $droppedValue = (int)$dropMatch[1];
            // Count the dropped d20 roll for the same character
            $this->currentSession['characters'][$character]['rolls']++;
            $this->currentSession['characters'][$character]['total_value'] += $droppedValue;
            $this->currentSession['characters'][$character]['average'] =
                round($this->currentSession['characters'][$character]['total_value'] /
                    $this->currentSession['characters'][$character]['rolls'], 2);
            // Update d20 stats for dropped value
            $d20Key = 'd20';
            if (!isset($this->currentSession['characters'][$character]['dice_stats']['dice_types'][$d20Key])) {
                $this->currentSession['characters'][$character]['dice_stats']['dice_types'][$d20Key] = [
                    'total_rolls' => 0,
                    'total_value' => 0,
                    'average' => 0,
                    'times_rolled' => 0,
                    'dice_type' => 20
                ];
            }
            $d20Stats = &$this->currentSession['characters'][$character]['dice_stats']['dice_types'][$d20Key];
            $d20Stats['times_rolled']++;
            $d20Stats['total_rolls']++;
            $d20Stats['total_value'] += $droppedValue;
            $d20Stats['average'] = $d20Stats['times_rolled'] > 0
                ? round($d20Stats['total_value'] / $d20Stats['times_rolled'], 2)
                : 0;
            // Track natural ones and twenties for d20
            if ($droppedValue === 1) {
                $this->currentSession['characters'][$character]['dice_stats']['natural_ones']++;
            } elseif ($droppedValue === 20) {
                $this->currentSession['characters'][$character]['dice_stats']['natural_twenties']++;
            }
        }
    }

    /**
     * Extract and record roll type and skill from a roll line.
     *
     * @param string $character
     * @param string $line
     * @return void
     */
    private function extractAndRecordRollTypeAndSkill(string $character, string $line): void
    {
        if (preg_match('/\[([A-Z]+)(?:\s+\([^)]+\))?(?:\s+([^\]]+))?\]/', $line, $matches)) {
            $rollType = $matches[1];
            $this->currentSession['characters'][$character]['roll_types'][$rollType] =
                ($this->currentSession['characters'][$character]['roll_types'][$rollType] ?? 0) + 1;
            // Special handling for skill checks
            if (preg_match('/\[SKILL\]\s+([^[]+?)(?:\s+\[[^\]]+\])*(?:<\/font>)?\s*\[[^\]]+\]/', $line, $skillMatches)) {
                $skill = trim($skillMatches[1]);
                $skill = strip_tags($skill);
                $skill = preg_replace('/\s+/', ' ', $skill);
                $skill = trim($skill);
                if (!empty($skill)) {
                    $this->currentSession['characters'][$character]['skills'][$skill] =
                        ($this->currentSession['characters'][$character]['skills'][$skill] ?? 0) + 1;
                    $this->debug[] = "Found skill check: {$skill} for character: {$character} (Current session total: {$this->currentSession['characters'][$character]['skills'][$skill]})";
                }
            }
        }
    }
} 