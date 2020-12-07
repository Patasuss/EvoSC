<?php


namespace EvoSC\Modules\Records;


use EvoSC\Classes\Hook;
use EvoSC\Classes\ManiaLinkEvent;
use EvoSC\Classes\Module;
use EvoSC\Classes\Template;
use EvoSC\Interfaces\ModuleInterface;
use EvoSC\Models\Player;

class Records extends Module implements ModuleInterface
{
    /**
     * @param string $mode
     * @param bool $isBoot
     * @return mixed|void
     */
    public static function start(string $mode, bool $isBoot = false)
    {
        if (isManiaPlanet()) {
            return;
        }

        Hook::add('PlayerConnect', [self::class, 'sendWidget']);
        ManiaLinkEvent::add('mle_ghost_notice', [self::class, 'mleGhostNotice']);
    }

    /**
     * @param Player $player
     * @throws \EvoSC\Exceptions\InvalidArgumentException
     */
    public static function sendWidget(Player $player)
    {
        Template::show($player, 'Records.manialink');
//        Template::show($player, 'Records.nadeo-records-mover');
    }

    /**
     * @param Player $player
     */
    public static function mleGhostNotice(Player $player)
    {
        warningMessage('Loading ghosts is not implemented yet, but it should be soon. Please use the default records to load ghosts for now.')->send($player);
    }
}