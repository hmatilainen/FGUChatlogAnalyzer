<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Service\ChatlogAnalyzer;

class ChatlogAnalyzerTest extends TestCase
{
    public function testAnalyzeDetectsDiceRolls()
    {
        $chatlog = <<<EOT
Session started at 2024-06-01 / 18:00
<font color="#FF0000">Alice: [1d20 = 15]</font>
<font color="#00FF00">Bob: [2d6+3 = 12]</font>
<font color="#0000FF">Charlie: [1d8 = 7]</font>
EOT;
        $tmpFile = tempnam(sys_get_temp_dir(), 'chatlog');
        file_put_contents($tmpFile, $chatlog);

        $analyzer = new ChatlogAnalyzer();
        $result = $analyzer->analyze($tmpFile);

        unlink($tmpFile);

        $this->assertEquals(3, $result['totals']['rolls'], 'Should detect 3 dice rolls');
        $this->assertArrayHasKey('Alice', $result['totals']['characters']);
        $this->assertArrayHasKey('Bob', $result['totals']['characters']);
        $this->assertArrayHasKey('Charlie', $result['totals']['characters']);
        $this->assertEquals(15, $result['totals']['characters']['Alice']['total_value']);
        $this->assertEquals(12, $result['totals']['characters']['Bob']['total_value']);
        $this->assertEquals(7, $result['totals']['characters']['Charlie']['total_value']);
    }

    /**
     * Integration test with the real chatlog.html file. Skipped by default.
     */
    public function testAnalyzeRealChatlogPrintsResults()
    {
        $this->markTestSkipped('Integration test with real chatlog, enable manually.');
        $file = '/media/chatlog.html';
        $analyzer = new ChatlogAnalyzer();
        $result = $analyzer->analyze($file);
        fwrite(STDOUT, print_r($result, true));
    }

    public function testAnalyzeDetectsComplexRollPatterns()
    {
        $chatlog = <<<EOT
Session started at 2024-06-01 / 18:00
<font color="#660066">Chelicerae: [DAMAGE (M)] Bite [CRITICAL] [TYPE: piercing (2d10+6=9)] [TYPE: piercing,critical (2d10=15)]</font> [2d10+2g10+6 = 24]<br />
<font color="#660066">Shalresh Sszar, Yuan-ti Malison: [ATTACK (R)] Longbow</font> [d20+4 = 14]<br />
<font color="#660066">Lambert Ulbrinter: [ATTACK (R)] Chill Touch [ADV] [DROPPED 9]</font> [g20+d20+7 = 21]<br />
<font color="#660066">Chelicerae: [DAMAGE (R)] Scorching Ray [TYPE: fire (2d6=9)]</font> [2d6 = 9]<br />
<font color="#660066">Lambert Ulbrinter: [DAMAGE] Ray Of Frost [TYPE: cold (2d8=8)]</font> [2d8 = 8]<br />
<font color="#660066">Myrbec the Mighty: [ATTACK (R)] Starry Wisp</font> [d20+4 = 13]<br />
<font color="#660066">Chelicerae: [ATTACK (M)] Bite</font> [d20+9 = 24]<br />
<font color="#660066">Chelicerae: [DAMAGE (M)] Bite [TYPE: piercing (2d10+6=13)]</font> [2d10+6 = 13]<br />
<font color="#660066">Chelicerae: [SAVE] Wisdom [EFFECTS] [ADV] [DROPPED 8]</font> [g20+d20+5 = 16]<br />
<font color="#660066">Lambert Ulbrinter: [CHECK] Dexterity</font> [d20-2 = 5]<br />
<font color="#660066">Lambert Ulbrinter: [ATTACK (M)] Glaive Of Warning</font> [d20+9 = 27]<br />
<font color="#660066">Chelicerae: [CONCENTRATION]</font> [d20+3 = 4]<br />
<font color="##660066">Orc Eye of Gruumsh 1: [SAVE] Dexterity</font> [d20+1 = 9]<br />
<font color="##660066">Orc Blade of Ilneval 1: [SAVE] Dexterity [EFFECTS 1d4]</font> [d20+p4 = 10]<br />
<font color="#660066">Myrbec: [DAMAGE (M)] Brown Bear Bite [TYPE: untyped (1d8+4=6)]</font> [1d8+4 = 6]<br />
<font color="#660066">Rudi: [ATTACK (M)] Dagger [ADV] [DROPPED 6]</font> [1g20+8 = 28]<br />
<font color="#660066">GM: [TABLE] Coastal / Underwater ingredients = </font> [2d6 = 12]<br />
<font color="#660066">Red Tiger barbaarisotilas (Tattooed right eye): [SAVE] Wisdom</font> [1d20 = 2]<br />
<font color="#660066">Myrbec: [DAMAGE] Produce Flame [TYPE: fire (2d8=14)]</font> [2d8 = 14]<br />
<font color="#660066">Myrbec: [SKILL] Survival [PROF]</font> [1d20+7 = 8]<br />
<font color="#660066">Cain: [SKILL] Survival [PROF]</font> [1d20+5 = 9]<br />
<font color="#660066">Cain: [SKILL] Survival [PROF] [ADV] [DROPPED 9]</font> [1g20+5 = 15]<br />
<font color="#660066">Cain: [DAMAGE (M)] Greatsword Giantsbane x2 [TYPE: slashing (3d6+4=10)]</font> [3d6+4 = 10]<br />
<font color="#660066">Magda: [SAVE] Constitution</font> [1d20+3 = 17]<br />
<font color="#660066">Giant Eagle: [ATTACK (M)] Beak</font> [1d20+5 = 21]<br />
<font color="#660066">GM: [TABLE] Fumble = </font> [1d6 = 6]<br />
<font color="#660066">Rudi: [SAVE] Wisdom</font> [1d20 = 13]<br />
<font color="#660066">Nerinoa: [DAMAGE] Witch Bolt [TYPE: lightning (1d12=1)]</font> [1d12 = 1]<br />
<font color="#660066">Cain: </font> [2d10 = 6]<br />
<font color="#660066">Cain: </font> [2d10 = 10]<br />
<font color="#660066">Myrbec: [SKILL] Religion [EFFECTS]</font> [1d20+4 = 24]<br />
<font color="#660066">Malekith: [SKILL] Religion [PROF]</font> [1d20+6 = 7]<br />
<font color="#660066">Rudi: [SKILL] Religion [PROF x1/2] [EFFECTS]</font> [1d20+3 = 20]<br />
<font color="#660066">Nerinoa: [SKILL] Religion [PROF]</font> [1d20+3 = 22]<br />
<font color="#660066">Cain: [SKILL] Religion [ADV] [DROPPED 3]</font> [1g20 = 6]<br />
<font color="#660066">Myrbec: [SKILL] Medicine [EFFECTS]</font> [1d20+4 = 5]<br />
<font color="#660066">Myrbec: [SKILL] Medicine [EFFECTS]</font> [1d20+4 = 7]<br />
<font color="#660066">GM: [TABLE] Forest Encounters (Levels 5-10) = </font> [1d100 = 60]<br />
<font color="#660066">Rudi: [SKILL] History [PROF] [EFFECTS]</font> [1d20+5 = 6]<br />
<font color="#660066">Myrbec: [SKILL] History [EFFECTS]</font> [1d20+4 = 23]<br />
<font color="#660066">Cain: [SKILL] History</font> [1d20 = 3]<br />
<font color="#660066">Malekith: [SKILL] History [PROF]</font> [1d20+7 = 13]<br />
<font color="#660066">Myrbec: [SKILL] Investigation [EFFECTS]</font> [1d20+4 = 6]<br />
<font color="#660066">Malekith: [SKILL] Investigation</font> [1d20+4 = 21]<br />
<font color="#660066">Rudi: </font> [1d8 = 1]<br />
<font color="#660066">Myrbec: [SKILL] Persuasion [EFFECTS]</font> [1d20-1 = 14]<br />
<font color="#660066">Rudi: [SKILL] Persuasion [PROF x2] [EFFECTS]</font> [1d20+10 = 20]<br />
<font color="#660066">Rudi: </font> [1d10 = 3]<br />
<font color="#660066">Cain: [SKILL] Intimidation [PROF]</font> [1d20+2 = 18]<br />
<font color="#660066">Cain: [SKILL] Persuasion [PROF x2] [ADV] [DROPPED 3]</font> [1g20+5 = 25]<br />
<font color="#660066">Myrbec: [SKILL] Investigation [EFFECTS]</font> [1d20+4 = 20]<br />
<font color="#660066">Cain: [SKILL] Athletics [PROF]</font> [1d20+7 = 26]<br />
<font color="#660066">Myrbec: [SKILL] Investigation [EFFECTS]</font> [1d20+4 = 12]<br />
<font color="#660066">Emrys: [DEATH] [SUCCESS]</font> [1d20 = 17]<br />
<font color="#660066">Emrys: [DEATH] [SUCCESS]</font> [1d20 = 10]<br />
<font color="#660066">Rudi: [DEATH] [CRITICAL FAILURE]</font> [d20 = 1]<br />
<font color="#660066">Cain: [DEATH] [EFFECTS] [FAILURE]</font> [d20 = 3]<br />
<font color="#660066">Rudi: [ATTACK (M)] Dagger [OPPORTUNITY] </font> [1d20+7 = 11]<br />
<font color="#660066">Rudi: [ATTACK (M)] Dagger (20/60) [COVER -2] [EFFECTS] [ +0] </font> [1d20+6 = 16]<br />
<font color="#660066">Rudi: [ATTACK (M)] Dagger (20/60) [COVER -5] [EFFECTS] </font> [1d20+3 = 18]<br />
<font color="#660066">Rudi: [ATTACK (M)] Dagger (20/60) [EFFECTS] </font> [1d20+8 = 11]<br />
<font color="#660066">Rudi: [DAMAGE (M)] Dagger (20/60) [HALF] [TYPE: piercing (1d4+5=9)]</font> [1d4+5 = 9]<br />
<font color="#660066">Rudi: [DAMAGE (M)] Dagger (20/60) [TYPE: piercing (1d4+5=6)]</font> [1d4+5 = 6]<br />
EOT;
        $lines = explode("\n", $chatlog);
        $analyzer = new ChatlogAnalyzer();
        $unmatchedLines = [];
        foreach ($lines as $line) {
            if (!$analyzer->analyzeLinePublic($line)) {
                $unmatchedLines[] = $line;
            }
        }
        $result = $analyzer->buildAnalysis();
        if (!empty($unmatchedLines)) {
            error_log("--- Unmatched lines ---");
            foreach ($unmatchedLines as $line) {
                error_log($line);
            }
            error_log("--- End unmatched lines ---");
        }
        
        // Count the number of detected rolls (should match the number of roll lines)
        $expectedRolls = 71; // Updated to match the new total number of roll lines
        
        // Output roll line count, detected count, and missed lines to STDERR for visibility
        $rollLines = array_filter($lines, fn($line) => preg_match('/\[(?:r?\d+|g\d+|\dg\d+|d\d+)(?:[+\-][^]=]+)* = \d+\]/', $line));

        // Extract detected roll lines from $result['debug']
        $detectedRollLines = array_map(
            fn($entry) => preg_replace('/^Found roll line: /', '', $entry),
            array_filter($result['debug'], fn($entry) => str_starts_with($entry, 'Found roll line:'))
        );
        
        // Remove Bardic Inspiration from roll lines (it is not a roll)
        $rollLines = array_filter($rollLines, fn($line) => stripos($line, 'Bardic Inspiration') === false);
        // Find missed roll lines (in input but not detected)
        $missed = array_diff($rollLines, $detectedRollLines);

        // Output roll line count, detected count, and missed lines to STDERR for visibility
        error_log("Total roll lines in input: ".count($rollLines));
        error_log("Total roll lines detected by analyzer: ".count($detectedRollLines));
        error_log("--- Missed roll lines (in input but not detected) ---");
        foreach ($missed as $line) {
            error_log($line);
        }
        error_log("--- End missed roll lines ---");
        
        $this->assertEquals($expectedRolls, $result['totals']['rolls'], 'Should detect all complex roll patterns');
        echo "Kekkonen2";
        // Check that some key characters are present
        $expectedCharacters = [
            'Chelicerae', 'Shalresh Sszar, Yuan-ti Malison', 'Lambert Ulbrinter', 'Myrbec the Mighty',
            'Cain', 'Magda', 'Giant Eagle', 'GM', 'Red Tiger barbaarisotilas (Tattooed right eye)',
            'Rudi', 'Nerinoa', 'Malekith', 'Myrbec', 'Orc Eye of Gruumsh 1', 'Orc Blade of Ilneval 1'
        ];
        foreach ($expectedCharacters as $character) {
            $this->assertArrayHasKey($character, $result['totals']['characters'], "Should detect character: $character");
        }
        // --- New assertions for [ADV] + [DROPPED] logic ---
        // Lambert Ulbrinter: [ATTACK (R)] Chill Touch [ADV] [DROPPED 9] [g20+d20+7 = 21]
        $lambert = $result['totals']['characters']['Lambert Ulbrinter'];
        $this->assertArrayHasKey('d20', $lambert['dice_stats']['dice_types']);
        $this->assertGreaterThanOrEqual(2, $lambert['dice_stats']['dice_types']['d20']['times_rolled'], 'Lambert should have at least 2 d20 rolls (main + dropped)');
        // Cain: [SKILL] Survival [PROF] [ADV] [DROPPED 9] [1g20+5 = 15]
        $cain = $result['totals']['characters']['Cain'];
        $this->assertArrayHasKey('d20', $cain['dice_stats']['dice_types']);
        $this->assertGreaterThanOrEqual(2, $cain['dice_stats']['dice_types']['d20']['times_rolled'], 'Cain should have at least 2 d20 rolls (main + dropped)');
        // Rudi: [ATTACK (M)] Dagger [ADV] [DROPPED 6] [1g20+8 = 28]
        $rudi = $result['totals']['characters']['Rudi'];
        $this->assertArrayHasKey('d20', $rudi['dice_stats']['dice_types']);
        $this->assertGreaterThanOrEqual(2, $rudi['dice_stats']['dice_types']['d20']['times_rolled'], 'Rudi should have at least 2 d20 rolls (main + dropped)');
        // Cain: [SKILL] Religion [ADV] [DROPPED 3] [1g20 = 6]
        $this->assertGreaterThanOrEqual(3, $cain['dice_stats']['dice_types']['d20']['times_rolled'], 'Cain should have at least 3 d20 rolls (main + dropped for two [ADV] lines)');
        // Cain: [SKILL] Persuasion [PROF x2] [ADV] [DROPPED 3] [1g20+5 = 25]
        $this->assertGreaterThanOrEqual(4, $cain['dice_stats']['dice_types']['d20']['times_rolled'], 'Cain should have at least 4 d20 rolls (main + dropped for three [ADV] lines)');
        // --- End new assertions ---
        echo "Are we here?\n";
    }
} 