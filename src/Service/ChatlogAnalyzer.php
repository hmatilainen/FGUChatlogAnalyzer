<?php

namespace App\Service;

class ChatlogAnalyzer
{
    private array $sessions = [];
    private ?array $currentSession = null;
    private array $debug = [];

    public function analyze(string $content): array
    {
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
        foreach ($this->sessions as $session) {
            $totalRolls += $session['total_rolls'];
            foreach ($session['characters'] as $charData) {
                $totalValue += $charData['total_value'];
            }
        }

        $analysis['totals']['rolls'] = $totalRolls;
        $analysis['totals']['average'] = $totalRolls > 0 ? round($totalValue / $totalRolls, 2) : 0;
        $analysis['sessions'] = $this->sessions;

        $this->debug[] = "Analysis complete. Total rolls: {$totalRolls}, Average: {$analysis['totals']['average']}";
        
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
            $this->debug[] = "No active session for roll: " . trim($line);
            return;
        }

        // Extract character name - handle both single and double hash in color codes
        if (preg_match('/<font color="#{1,2}[0-9A-Fa-f]{6}">([^:]+):/', $line, $matches)) {
            $character = trim($matches[1]);
        } else {
            $this->debug[] = "Could not extract character name from: " . trim($line);
            return;
        }

        // Extract roll value - handle more complex roll formats
        if (preg_match('/\[(?:r?\d+)?d\d+(?:\+\d+)?(?:\+d\d+)?(?:\+\d+)? = (\d+)\]/', $line, $matches)) {
            $rollValue = (int)$matches[1];
            $this->debug[] = "Found roll value: {$rollValue} for character: {$character}";
        } else {
            $this->debug[] = "Could not extract roll value from: " . trim($line);
            return;
        }

        // Initialize character data if not exists
        if (!isset($this->currentSession['characters'][$character])) {
            $this->currentSession['characters'][$character] = [
                'rolls' => 0,
                'total_value' => 0,
                'average' => 0,
                'roll_types' => [],
                'skills' => []
            ];
        }

        // Update session totals
        $this->currentSession['total_rolls']++;
        $this->currentSession['total_value'] += $rollValue;

        // Update character totals
        $this->currentSession['characters'][$character]['rolls']++;
        $this->currentSession['characters'][$character]['total_value'] += $rollValue;
        $this->currentSession['characters'][$character]['average'] = 
            round($this->currentSession['characters'][$character]['total_value'] / 
                  $this->currentSession['characters'][$character]['rolls'], 2);

        // Extract roll type and skill if present
        if (preg_match('/\[(ATTACK|DAMAGE|SKILL|SAVE|CHECK|TOWER|CAST)(?:\s+\([^)]+\))?(?:\s+([^]]+))?\]/', $line, $matches)) {
            $rollType = $matches[1];
            $this->currentSession['characters'][$character]['roll_types'][$rollType] = 
                ($this->currentSession['characters'][$character]['roll_types'][$rollType] ?? 0) + 1;

            if (isset($matches[2])) {
                $skill = trim($matches[2]);
                $this->currentSession['characters'][$character]['skills'][$skill] = 
                    ($this->currentSession['characters'][$character]['skills'][$skill] ?? 0) + 1;
            }
        }

        $this->debug[] = "Updated session totals - Total rolls: {$this->currentSession['total_rolls']}, Total value: {$this->currentSession['total_value']}";
    }
} 