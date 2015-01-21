<?php
/**
 * Copyright (C) 2015 David Young
 * 
 * Defines the about command
 */
namespace RDev\Console\Commands;
use RDev\Console\Responses;
use RDev\Console\Responses\Formatters;

class About extends Command
{
    /** @var string The template for the output */
    private static $template = <<<EOF
-----------------------------
About RDev Console {{version}}
-----------------------------
Commands:
{{commands}}
EOF;
    /** @var Commands The list of commands registered */
    private $commands = null;
    /** @var Formatters\Padding The space padding formatter to use */
    private $spacePaddingFormatter  = null;
    /** @var string The version number of the application */
    private $applicationVersion = "Unknown";

    /**
     * @param Commands $commands The list of commands
     * @param Formatters\Padding $spacePaddingFormatter The space padding formatter to use
     * @param string $applicationVersion The version number of the application
     */
    public function __construct(Commands &$commands, Formatters\Padding $spacePaddingFormatter, $applicationVersion)
    {
        parent::__construct();

        $this->commands = $commands;
        $this->spacePaddingFormatter = $spacePaddingFormatter;
        $this->applicationVersion = $applicationVersion;
    }

    /**
     * {@inheritdoc}
     */
    protected function define()
    {
        $this->setName("about")
            ->setDescription("Describes the RDev console application");
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(Responses\IResponse $response)
    {
        // Compile the template
        $commandText = $this->getCommandText();
        $compiledTemplate = self::$template;
        $compiledTemplate = str_replace("{{commands}}", $commandText, $compiledTemplate);
        $compiledTemplate = str_replace("{{version}}", $this->applicationVersion, $compiledTemplate);

        $response->writeln($compiledTemplate);
    }

    /**
     * Converts commands to text
     *
     * @return string The commands as text
     */
    private function getCommandText()
    {
        if(count($this->commands->getAll()) == 0)
        {
            return "   No commands";
        }

        /**
         * Sorts the commands by name
         *
         * @param ICommand $a
         * @param ICommand $b
         * @return int The result of the comparison
         */
        $sort = function($a, $b)
        {
            return $a->getName() < $b->getName() ? -1 : 1;
        };

        $commands = $this->commands->getAll();
        usort($commands, $sort);
        $commandTexts = [];

        // Figure out the longest command name
        foreach($commands as $command)
        {
            $commandTexts[] = [$command->getName(), " - " . $command->getDescription()];
        }

        return $this->spacePaddingFormatter->format($commandTexts, function($line)
        {
            return "   " . $line[0] . $line[1];
        });
    }
}