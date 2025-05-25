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

        // Create a default session if none is found
        $this->startNewSession(date('Y-m-d'), date('H:i'));

        foreach ($lines as $line) {
            // Check for session start
            if (preg_match('/Session started at (\d{4}-\d{2}-\d{2}) \/ (\d{2}:\d{2})/', $line, $matches)) {
                $this->startNewSession($matches[1], $matches[2]);
                $this->debug[] = "Found session start: {$matches[1]} at {$matches[2]}";
                continue;
            }

            // Check for roll lines
            if (preg_match('/\[.*d.*\]/', $line)) {
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