<?php

use esc\Classes\Database;
use esc\Classes\Hook;
use esc\Classes\Timer;
use esc\Models\Player;
use Illuminate\Database\Schema\Blueprint;

class Statistics
{
    /**
     * Initialize statistics
     */
    public static function init()
    {
        require_once __DIR__ . '/Models/Stats.php';

        self::createTables();

        Hook::add('PlayerConnect', 'Statistics::playerConnect');
        Hook::add('PlayerFinish', 'Statistics::playerFinish');
        Hook::add('PlayerRateMap', 'Statistics::playerRateMap');
        Hook::add('PlayerLocal', 'Statistics::playerLocal');
        Hook::add('PlayerDonate', 'Statistics::playerDonate');
        Hook::add('EndMatch', 'Statistics::endMatch');

        Timer::create('update-playtimes', 'Statistics::updatePlaytimes', '1m');
    }

    /**
     * @param Player $player
     */
    public static function playerConnect(Player $player)
    {
        $player->stats()->increment('Visits');
    }

    /**
     * @param Player $player
     * @param int $score
     */
    public static function playerFinish(Player $player, int $score)
    {
        if ($score < 3000) {
            //ignore times under 3 seconds
            return;
        }

        $player->stats()->increment('Finishes');
    }

    /**
     * @param Player $player
     * @param Karma $karma
     */
    public static function playerRateMap(Player $player, Karma $karma)
    {
        $player->Ratings = $player->ratings()->count();
        $player->save();
    }

    /**
     * @param Player $player
     * @param LocalRecord $local
     */
    public static function playerLocal(Player $player, LocalRecord $local)
    {
        $player->Locals = $player->locals()->count();
        $player->save();
    }

    /**
     * Increment playtimes each minute
     */
    public static function updatePlaytimes()
    {
        foreach (onlinePlayers() as $player) {
            $player->increment('Playtime');
        }

        Timer::create('update-playtimes', 'Statistics::updatePlaytimes', '1m', true);
    }

    /**
     * @param array ...$args
     */
    public static function endMatch(...$args)
    {
        $bestPlayer = finishPlayers()->sortBy('Score')->first();

        if ($bestPlayer) {
            $bestPlayer->stats()->increment('Wins');
        }
    }

    /**
     * @param Player $player
     * @param int $amount
     */
    public static function playerDonate(Player $player, int $amount)
    {
        $player->Donations += $amount;
        $player->save();
    }

    /**
     * Create the database table
     */
    public static function createTables()
    {
        Database::create('stats', function (Blueprint $table) {
            $table->integer('Player')->primary();
            $table->integer('Visits')->default(0);
            $table->integer('Playtime')->default(0);
            $table->integer('Finishes')->default(0);
            $table->integer('Locals')->default(0);
            $table->integer('Ratings')->default(0);
            $table->integer('Wins')->default(0);
            $table->integer('Donations')->default(0);
            $table->timestamps();
        });
    }
}