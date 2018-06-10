<?php

include 'autoload.php';
include 'bootstrap.php';

use esc\Classes\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EscRun extends Command
{
    protected function configure()
    {
        $this->setName('run')->setDescription('Run Evo Server Controller');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->start($output);
    }

    private function start(OutputInterface $output)
    {
        register_shutdown_function(function () {
            $error = error_get_last();

            echo $error['type'] . "\n";

            // fatal error, E_ERROR === 1
            if ($error['type'] === E_ERROR) {
                $crashReport = collect();
                $crashReport->put('file', $error['file']);
                $crashReport->put('line', $error['line']);
                $crashReport->put('message', $error['message']);

                if (!is_dir(__DIR__ . '/../crash-reports')) {
                    mkdir(__DIR__ . '/../crash-reports');
                }

                $filename = sprintf(__DIR__ . '/../crash-reports/%s.json', date('Y-m-d_Hi', time()));
                file_put_contents($filename, $crashReport->toJson());
            }
        });

        esc\Classes\Log::info("Starting...");

        startEsc($output);

        if (isVerbose()) {
            Log::logAddLine('BOOT', 'Booting core finished.', true);
        }

        bootModules();

        if (isVerbose()) {
            Log::logAddLine('BOOT', 'Booting modules finished.', true);
        }

        beginMap();

        //Set connected players online
        $onlinePlayersLogins = collect(\esc\Classes\Server::getRpc()->getPlayerList())->pluck('login');
        $onlinePlayers       = esc\Models\Player::whereIn('Login', $onlinePlayersLogins)->get();
        esc\Models\Player::whereNotIn('Login', $onlinePlayersLogins)->where('player_id', '>', 0)->update(['player_id' => 0]);
        foreach ($onlinePlayers as $player) {
            \esc\Classes\Hook::fire('PlayerConnect', $player);

            if (isVeryVerbose()) {
                Log::logAddLine('BOOT', 'Connecting player ' . $player, true);
            }
        }

        //Enable mode script rpc-callbacks else you wont get stuf flike checkpoints and finish
        \esc\Classes\Server::triggerModeScriptEventArray('XmlRpc.EnableCallbacks', ['true']);

        if (isDebug()) {
            $waitTimes = collect();
            $min       = 10000;
            $max       = 0;
            $waitTime  = config('server.controller-interval');
        }

        while (true) {
            try {
                esc\Classes\Timer::startCycle();

                try {
                    \esc\Controllers\EventController::handleCallbacks(esc\Classes\Server::executeCallbacks());
                } catch (Exception $e) {
                    $crashReport = collect();
                    $crashReport->put('file', $e->getFile());
                    $crashReport->put('line', $e->getLine());
                    $crashReport->put('message', $e->getMessage() . "\n" . $e->getTraceAsString());

                    if (!is_dir(__DIR__ . '/../crash-reports')) {
                        mkdir(__DIR__ . '/../crash-reports');
                    }

                    $filename = sprintf(__DIR__ . '/../crash-reports/%s.json', date('Y-m-d_Hi', time()));
                    file_put_contents($filename, $crashReport->toJson());
                }

                $pause = esc\Classes\Timer::getNextCyclePause();

                if (isDebug()) {
                    $runTime = (($waitTime * 1000) - $pause) / 1000;
                    $waitTimes->push($pause / 1000);
                    $average = $waitTimes->avg();
                    if ($pause / 1000 < $min) {
                        $min = $pause / 1000;
                    }
                    if ($pause / 1000 > $max) {
                        $max = $pause / 1000;
                    }
                    \esc\Classes\Log::logAddLine('cycle', sprintf('Finished in %.2fms wait %.2fms (Min: %.2fms, Max: %.2fms, Avg: %.2fms)', $runTime, $pause / 1000, $min, $max, $average));
                }

                usleep($pause);
            } catch (\Maniaplanet\DedicatedServer\Xmlrpc\TransportException $e) {
                Log::logAddLine('XmlRpc', $e->getMessage());
            }
        }
    }
}