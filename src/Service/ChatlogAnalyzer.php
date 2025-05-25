<?php

namespace App\Service;

class ChatlogAnalyzer
{
    private array $sessions = [];
    private ?array $currentSession = null;
    private array $debug = [];

    public function analyze(string $filepath): array
    {
        $content = file_get_contents($filepath);
        if (!$content) {
            throw new \RuntimeException('Could not read file contents');
        }

        $this->debug = [];
        $this->debug[] = "Starting analysis...";
        
        $lines = explode("\n", $content);
        $analysis = [
            'totals' => [
                'rolls' => 0,
                'average' => 0,
                'characters' => []
            ],
            'sessions' => [],
            'debug' => &$this->debug
        ];

        $foundFirstSession = false;
        $foundRollsBeforeSession = false;

        foreach ($lines as $line) {
            // Check for session start - handle both formats
            if (preg_match('/Session started at (\d{4}-\d{2}-\d{2}) \/ (\d{2}:\d{2})/', $line, $matches) ||
                preg_match('/Chat log started at (\d{1,2}\.\d{1,2}\.\d{4}) \/ (\d{2}:\d{2}:\d{2})/', $line, $matches)) {
                
                if (!$foundFirstSession) {
                    $foundFirstSession = true;
                    // If we found rolls before the first session, create a default session
                    if ($foundRollsBeforeSession) {
                        $this->startNewSession('Unknown', 'Unknown');
                        $this->debug[] = "Created default session for rolls before first explicit session";
                    }
                }

                // Convert date format if needed
                $date = $matches[1];
                $time = $matches[2];
                
                // Convert from DD.MM.YYYY to YYYY-MM-DD if needed
                if (strpos($date, '.') !== false) {
                    $dateParts = explode('.', $date);
                    $date = sprintf('%04d-%02d-%02d', $dateParts[2], $dateParts[1], $dateParts[0]);
                }

                // Remove seconds from time if present
                if (strlen($time) > 5) {
                    $time = substr($time, 0, 5);
                }

                $this->startNewSession($date, $time);
                $this->debug[] = "Found session start: {$date} at {$time}";
                continue;
            }

            // Check for roll lines
            if (preg_match('/\[.*d.*\]/', $line)) {
                if (!$foundFirstSession) {
                    $foundRollsBeforeSession = true;
                }
                $this->debug[] = "Found roll line: " . trim($line);
                $this->processRoll($line);
            }
        }

        // Finalize the last session
        $this->finalizeSession();

        // Calculate totals
        $totalRolls = 0;
        $totalValue = 0;
        $characterTotals = [];

        foreach ($this->sessions as $session) {
            $totalRolls += $session['total_rolls'];
            
            foreach ($session['characters'] as $charName => $charData) {
                // Initialize character totals if not exists
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

                // Update character totals
                $characterTotals[$charName]['rolls'] += $charData['rolls'];
                $characterTotals[$charName]['total_value'] += $charData['total_value'];
                $characterTotals[$charName]['average'] = 
                    round($characterTotals[$charName]['total_value'] / $characterTotals[$charName]['rolls'], 2);

                // Merge roll types
                foreach ($charData['roll_types'] as $type => $count) {
                    $characterTotals[$charName]['roll_types'][$type] = 
                        ($characterTotals[$charName]['roll_types'][$type] ?? 0) + $count;
                }

                // Merge skills
                foreach ($charData['skills'] as $skill => $count) {
                    $characterTotals[$charName]['skills'][$skill] = 
                        ($characterTotals[$charName]['skills'][$skill] ?? 0) + $count;
                }

                // Merge dice statistics
                foreach ($charData['dice_stats']['dice_types'] as $diceType => $diceData) {
                    $diceKey = "d{$diceType}";
                    if (!isset($characterTotals[$charName]['dice_stats']['dice_types'][$diceKey])) {
                        $characterTotals[$charName]['dice_stats']['dice_types'][$diceKey] = [
                            'total_rolls' => 0,
                            'total_value' => 0,
                            'average' => 0,
                            'times_rolled' => 0,
                            'dice_type' => $diceType
                        ];
                    }

                    $diceStats = &$characterTotals[$charName]['dice_stats']['dice_types'][$diceKey];
                    $diceStats['times_rolled'] += $diceData['times_rolled'];
                    $diceStats['total_rolls'] += $diceData['total_rolls'];
                    $diceStats['total_value'] += $diceData['total_value'];
                    $diceStats['average'] = $diceStats['times_rolled'] > 0 
                        ? round($diceStats['total_value'] / $diceStats['times_rolled'], 2)
                        : 0;
                }

                // Track natural ones and twenties for d20
                $characterTotals[$charName]['dice_stats']['natural_ones'] += $charData['dice_stats']['natural_ones'];
                $characterTotals[$charName]['dice_stats']['natural_twenties'] += $charData['dice_stats']['natural_twenties'];
            }
        }

        // Add final skill totals to debug
        foreach ($characterTotals as $charName => $charData) {
            foreach ($charData['skills'] as $skill => $count) {
                $this->debug[] = "Final skill total: {$skill} for character: {$charName} (Total across all sessions: {$count})";
            }
        }

        $analysis['totals']['rolls'] = $totalRolls;
        $analysis['totals']['average'] = $totalRolls > 0 ? round($totalValue / $totalRolls, 2) : 0;
        $analysis['totals']['characters'] = $characterTotals;
        $analysis['sessions'] = $this->sessions;

        $this->debug[] = "Analysis complete. Total rolls: {$totalRolls}, Average: {$analysis['totals']['average']}, Characters: " . count($characterTotals);
        
        return $analysis;
    }

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

    private function finalizeSession(): void
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

    private function processRoll(string $line): void
    {
        if (!$this->currentSession) {
            return;
        }

        // Extract character name - handle both single and double hash in color codes
        if (preg_match('/<font color="#{1,2}[0-9A-Fa-f]{6}">([^:]+):/', $line, $matches)) {
            $character = trim($matches[1]);
        } else {
            return;
        }

        // Extract roll value and dice information
        if (preg_match('/\[(?:r?(\d+))?d(\d+)(?:\+(\d+))?(?:\+d\d+)?(?:\+\d+)? = (\d+)\]/', $line, $matches)) {
            $numDice = (int)($matches[1] ?? 1);
            $diceType = (int)$matches[2];
            $bonus = (int)($matches[3] ?? 0);
            $totalValue = (int)$matches[4];
            $actualRoll = $totalValue - $bonus;
        } else {
            return;
        }

        // Initialize character data if not exists
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
        if (!isset($this->currentSession['characters'][$character]['dice_stats']['dice_types'][$diceKey])) {
            $this->currentSession['characters'][$character]['dice_stats']['dice_types'][$diceKey] = [
                'total_rolls' => 0,
                'total_value' => 0,
                'average' => 0,
                'times_rolled' => 0,
                'dice_type' => $diceType
            ];
        }

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

        // Extract roll type and skill if present
        if (preg_match('/\[(ATTACK|DAMAGE|SKILL|SAVE|CHECK|TOWER|CAST)(?:\s+\([^)]+\))?(?:\s+([^]]+))?\]/', $line, $matches)) {
            $rollType = $matches[1];
            $this->currentSession['characters'][$character]['roll_types'][$rollType] = 
                ($this->currentSession['characters'][$character]['roll_types'][$rollType] ?? 0) + 1;

            // Special handling for skill checks - simplified to just find [SKILL] and skill name
            if (preg_match('/\[SKILL\]\s+([^[]+?)(?:\s+\[[^\]]+\])*(?:<\/font>)?\s*\[[^\]]+\]/', $line, $skillMatches)) {
                $skill = trim($skillMatches[1]);
                // Remove any HTML tags from the skill name
                $skill = strip_tags($skill);
                // Clean up any remaining whitespace
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