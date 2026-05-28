<?php

namespace App\Helpers;

class TimeLimitHelper
{
    /**
     * Parse time limit format [wdhm] to seconds
     * 
     * Format examples:
     * - 1h = 1 hour = 3600 seconds
     * - 12h = 12 hours = 43200 seconds
     * - 1d = 1 day = 86400 seconds
     * - 7d = 7 days = 604800 seconds
     * - 1w = 1 week = 604800 seconds
     * - 4w3d = 4 weeks + 3 days = 2678400 seconds
     * 
     * @param string $timeLimit Format: [wdhm] e.g., "1d", "12h", "4w3d"
     * @return int Total seconds
     */
    public static function parseToSeconds($timeLimit)
    {
        if (empty($timeLimit)) {
            return 0;
        }

        $totalSeconds = 0;
        
        // Match all patterns like: 4w, 3d, 12h, 30m
        preg_match_all('/(\d+)([wdhm])/', $timeLimit, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $value = (int) $match[1];
            $unit = $match[2];
            
            switch ($unit) {
                case 'w': // weeks
                    $totalSeconds += $value * 7 * 24 * 3600;
                    break;
                case 'd': // days
                    $totalSeconds += $value * 24 * 3600;
                    break;
                case 'h': // hours
                    $totalSeconds += $value * 3600;
                    break;
                case 'm': // minutes
                    $totalSeconds += $value * 60;
                    break;
            }
        }
        
        return $totalSeconds;
    }

    /**
     * Parse time limit to MikroTik format (HH:MM:SS)
     * 
     * @param string $timeLimit Format: [wdhm] e.g., "1d", "12h", "4w3d"
     * @return string MikroTik format: "HH:MM:SS"
     */
    public static function parseToMikrotikFormat($timeLimit)
    {
        $seconds = self::parseToSeconds($timeLimit);
        
        if ($seconds == 0) {
            return '';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    /**
     * Convert seconds to human readable format
     * 
     * @param int $seconds
     * @return string Human readable format
     */
    public static function secondsToHuman($seconds)
    {
        if ($seconds == 0) {
            return 'Unlimited';
        }

        $weeks = floor($seconds / (7 * 24 * 3600));
        $days = floor(($seconds % (7 * 24 * 3600)) / (24 * 3600));
        $hours = floor(($seconds % (24 * 3600)) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        $parts = [];
        
        if ($weeks > 0) {
            $parts[] = $weeks . 'w';
        }
        if ($days > 0) {
            $parts[] = $days . 'd';
        }
        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }
        if ($minutes > 0) {
            $parts[] = $minutes . 'm';
        }
        
        return implode(' ', $parts) ?: '0m';
    }

    /**
     * Validate time limit format
     * 
     * @param string $timeLimit
     * @return bool
     */
    public static function validate($timeLimit)
    {
        if (empty($timeLimit)) {
            return true; // Empty is valid (unlimited)
        }

        // Match format: [number][w|d|h|m]
        return preg_match('/^(\d+[wdhm])+$/', $timeLimit);
    }

    /**
     * Check if time limit is less than validity
     * 
     * @param string $timeLimit Format: [wdhm]
     * @param string $validity Date string or days
     * @return bool
     */
    public static function isLessThanValidity($timeLimit, $validity)
    {
        if (empty($timeLimit)) {
            return true; // No time limit means always valid
        }

        $timeLimitSeconds = self::parseToSeconds($timeLimit);
        
        // If validity is in days format (e.g., "30d")
        if (preg_match('/^(\d+)d$/', $validity, $matches)) {
            $validitySeconds = (int)$matches[1] * 24 * 3600;
        } 
        // If validity is a date
        elseif (strtotime($validity)) {
            $validitySeconds = strtotime($validity) - time();
        } 
        // Default: unlimited
        else {
            return true;
        }
        
        return $timeLimitSeconds < $validitySeconds;
    }

    /**
     * Examples and test cases
     */
    public static function examples()
    {
        return [
            '1h' => '1 hour (3600 seconds)',
            '12h' => '12 hours (43200 seconds)',
            '1d' => '1 day (86400 seconds)',
            '7d' => '7 days (604800 seconds)',
            '30d' => '30 days (2592000 seconds)',
            '1w' => '1 week (604800 seconds)',
            '4w' => '4 weeks (2419200 seconds)',
            '4w3d' => '4 weeks + 3 days (2678400 seconds)',
            '1w2d3h' => '1 week + 2 days + 3 hours (788400 seconds)',
        ];
    }
}

