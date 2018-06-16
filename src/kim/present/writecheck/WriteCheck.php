<?php

namespace kim\present\writecheck;

use pocketmine\item\Item;
use pocketmine\nbt\tag\IntTag;
use pocketmine\plugin\PluginBase;
use kim\present\writecheck\command\PoolCommand;
use kim\present\writecheck\command\subcommands\{
  WriteSubCommand, LangSubCommand, ReloadSubCommand
};
use kim\present\writecheck\listener\PlayerEventListener;
use kim\present\writecheck\util\{
  Translation, Utils
};

class WriteCheck extends PluginBase{

    /** @var WriteCheck */
    private static $instance = null;

    /** @var string */
    public static $prefix = '';

    /** @var PoolCommand */
    private $command = null;

    /** @return WriteCheck */
    public static function getInstance() : WriteCheck{
        return self::$instance;
    }

    public function onLoad() : void{
        if (self::$instance === null) {
            self::$instance = $this;
            Translation::loadFromResource($this->getResource('lang/eng.yml'), true);
        }
    }

    public function onEnable() : void{
        $this->load();
        $this->getServer()->getPluginManager()->registerEvents(new PlayerEventListener(), $this);
    }

    public function load() : void{
        $dataFolder = $this->getDataFolder();
        if (!file_exists($dataFolder)) {
            mkdir($dataFolder, 0777, true);
        }

        $langfilename = $dataFolder . 'lang.yml';
        if (!file_exists($langfilename)) {
            $resource = $this->getResource('lang/eng.yml');
            fwrite($fp = fopen("{$dataFolder}lang.yml", "wb"), $contents = stream_get_contents($resource));
            fclose($fp);
            Translation::loadFromContents($contents);
        } else {
            Translation::load($langfilename);
        }

        self::$prefix = Translation::translate('prefix');
        $this->reloadCommand();
    }

    public function reloadCommand() : void{
        if ($this->command == null) {
            $this->command = new PoolCommand($this, 'wcheck');
            $this->command->createSubCommand(WriteSubCommand::class);
            $this->command->createSubCommand(LangSubCommand::class);
            $this->command->createSubCommand(ReloadSubCommand::class);
        }
        $this->command->updateTranslation();
        $this->command->updateSudCommandTranslation();
        if ($this->command->isRegistered()) {
            $this->getServer()->getCommandMap()->unregister($this->command);
        }
        $this->getServer()->getCommandMap()->register(strtolower($this->getName()), $this->command);
    }

    /**
     * @param string $name = ''
     *
     * @return PoolCommand
     */
    public function getCommand(string $name = '') : PoolCommand{
        return $this->command;
    }

    /** @param PoolCommand $command */
    public function setCommand(PoolCommand $command) : void{
        $this->command = $command;
    }

    /**
     * @param int $amount
     * @param int $count
     *
     * @return Item
     */
    public function getCheck(int $amount, int $count = 1) : Item{
        $paper = Item::get(Item::PAPER, 0xff, $count);
        $paper->setNamedTagEntry(new IntTag('whitecheck-amount', $amount));
        $paper->setCustomName(Translation::translate('check-name', $amount));
        $lore = [];
        foreach (Translation::getArray('check-lore') as $key => $line) {
            $lore[] = strtr($line, Utils::listToPairs([$amount]));
        }
        $paper->setLore($lore);
        return $paper;
    }

    /**
     * @param Item $item
     *
     * @return int|null
     */
    public function getAmount(Item $item) : ?int{
        if ($item->getId() == Item::PAPER && $item->getDamage() === 0xff) {
            $amount = $item->getNamedTag()->getTagValue('whitecheck-amount', IntTag::class, -1);
            if ($amount !== -1) {
                return $amount;
            }
        }
        return null;
    }

    /**
     * @param Item $item
     *
     * @return bool
     */
    public function isCheck(Item $item) : bool{
        return $this->getAmount($item) !== null;
    }
}