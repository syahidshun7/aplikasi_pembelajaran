<?php

namespace App\Services;

/**
 * Central leveling system.
 *
 * Uses a progressive XP curve so each level costs more than the previous one
 * (classic RPG feel). Titles/ranks are derived from the level so the UI can
 * show a meaningful label instead of only a number.
 */
class LevelingService
{
    public const MAX_LEVEL = 100;

    /**
     * XP required to go from level 1 -> 2.
     */
    private const BASE_EXP = 1000;

    /**
     * Geometric growth rate applied per level.
     * 1.03 means each level costs ~3% more than the previous one.
     */
    private const GROWTH_RATE = 1.03;

    /**
     * Minimum level -> title mapping (inclusive).
     */
    private const TITLES = [
        1   => 'Novice',
        5   => 'Apprentice',
        10  => 'Journeyman',
        15  => 'Adept',
        20  => 'Skilled',
        25  => 'Expert',
        30  => 'Veteran',
        35  => 'Elite',
        40  => 'Master',
        50  => 'Grandmaster',
        60  => 'Champion',
        70  => 'Hero',
        80  => 'Legend',
        90  => 'Mythic',
        100 => 'Transcendent',
    ];

    /** @var array<int,int>|null */
    private static ?array $thresholdCache = null;

    /**
     * Cumulative XP required to reach a given level.
     */
    public static function expForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        if ($level > self::MAX_LEVEL) {
            $level = self::MAX_LEVEL;
        }

        $thresholds = self::buildThresholds();

        return $thresholds[$level] ?? 0;
    }

    /**
     * XP needed to go from $level to $level + 1.
     */
    public static function expBetweenLevels(int $level): int
    {
        if ($level < 1) {
            $level = 1;
        }

        if ($level >= self::MAX_LEVEL) {
            return 0;
        }

        return self::expForLevel($level + 1) - self::expForLevel($level);
    }

    /**
     * Derive the current level from total lifetime XP.
     */
    public static function levelFromExp(int $totalExp): int
    {
        if ($totalExp <= 0) {
            return 1;
        }

        $thresholds = self::buildThresholds();

        $level = 1;
        for ($i = 2; $i <= self::MAX_LEVEL; $i++) {
            if ($totalExp >= $thresholds[$i]) {
                $level = $i;
                continue;
            }

            break;
        }

        return $level;
    }

    /**
     * Full progress payload for a given total XP, designed to be consumed
     * directly by the frontend (profile, navbar, leaderboard, etc).
     *
     * @return array{
     *     level:int,
     *     title:string,
     *     total_exp:int,
     *     current_level_exp:int,
     *     next_level_exp:int,
     *     exp_in_level:int,
     *     exp_needed:int,
     *     progress_percent:int,
     *     is_max_level:bool
     * }
     */
    public static function progress(int $totalExp): array
    {
        $totalExp = max(0, $totalExp);
        $level = self::levelFromExp($totalExp);
        $currentLevelExp = self::expForLevel($level);
        $nextLevelExp = self::expForLevel(min($level + 1, self::MAX_LEVEL));
        $expInLevel = $totalExp - $currentLevelExp;
        $expNeeded = $nextLevelExp - $currentLevelExp;

        if ($level >= self::MAX_LEVEL) {
            return [
                'level' => self::MAX_LEVEL,
                'title' => self::titleForLevel(self::MAX_LEVEL),
                'total_exp' => $totalExp,
                'current_level_exp' => $currentLevelExp,
                'next_level_exp' => $currentLevelExp,
                'exp_in_level' => 0,
                'exp_needed' => 0,
                'progress_percent' => 100,
                'is_max_level' => true,
            ];
        }

        $progressPercent = $expNeeded > 0
            ? (int) floor(($expInLevel / $expNeeded) * 100)
            : 0;

        return [
            'level' => $level,
            'title' => self::titleForLevel($level),
            'total_exp' => $totalExp,
            'current_level_exp' => $currentLevelExp,
            'next_level_exp' => $nextLevelExp,
            'exp_in_level' => max(0, $expInLevel),
            'exp_needed' => max(0, $expNeeded),
            'progress_percent' => min(100, max(0, $progressPercent)),
            'is_max_level' => false,
        ];
    }

    public static function titleForLevel(int $level): string
    {
        $title = 'Novice';

        foreach (self::TITLES as $threshold => $name) {
            if ($level >= $threshold) {
                $title = $name;
            }
        }

        return $title;
    }

    /**
     * @return array<int,int>
     */
    private static function buildThresholds(): array
    {
        if (self::$thresholdCache !== null) {
            return self::$thresholdCache;
        }

        $thresholds = [1 => 0];
        $cumulative = 0;

        for ($i = 2; $i <= self::MAX_LEVEL; $i++) {
            $required = (int) round(self::BASE_EXP * pow(self::GROWTH_RATE, $i - 2));
            $cumulative += $required;
            $thresholds[$i] = $cumulative;
        }

        self::$thresholdCache = $thresholds;

        return $thresholds;
    }
}
