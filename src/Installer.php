<?php


namespace MinhD\PHPGenerator;

use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Script\Event;

class Installer
{

    private static $values;

    /**
     * Pre installation Script
     *
     * @param Event $event
     */
    public static function preInstall(Event $event)
    {
        // gather information
        $io = $event->getIO();

        self::$values['vendor'] = self::ask(
            $io, 'Vendor', 'MinhD'
        );

        self::$values['package'] = self::ask(
            $io, 'Package', 'MyPackage'
        );

        self::$values['composer_name'] = self::ask(
            $io, 'Package Name',
            self::camel2dashed(self::$values['vendor']) . '/' . self::camel2dashed(self::$values['package'])
        );

        self::$values['name'] = self::ask(
            $io, 'Author', trim(`git config --global user.name`)
        );

        self::$values['email'] = self::ask(
            $io, 'Email', trim(`git config --global user.email`)
        );

        self::$values['description'] = self::ask(
            $io, 'Description', ''
        );

        // process the composer file
        $composerFile = new JsonFile(Factory::getComposerFile());
        $composerData = self::processComposerFile($composerFile);
        $composerFile->write($composerData);

        $io->write("<info>composer.json for {$composerData['name']} is created.\n</info>");
    }

    /**
     * Read the composer file then process the data
     * Returns processed data
     *
     * @param JsonFile $file
     * @return mixed
     */
    private static function processComposerFile(JsonFile $file)
    {
        $composer = $file->read();

        // remove
        unset($composer['autoload']['files']);
        unset($composer['scripts']['pre-install-cmd']);
        unset($composer['scripts']['pre-update-cmd']);
        unset($composer['scripts']['post-create-project-cmd']);

        // replace
        $composer['name'] = self::$values['composer_name'];
        $composer['authors'] = [
            ['name' => self::$values['name'], 'email' => self::$values['email']]
        ];
        $composer['description'] = self::$values['description'];
        $composer['autoload']['psr-4'] = [
            self::$values['vendor'].'\\'.self::$values['package'].'\\' => "src/"
        ];

        return $composer;
    }

    /**
     * Post Installation script
     *
     * @param Event|null $event
     */
    public static function postInstall(Event $event = null)
    {

        // for each file, replace the content of the file
        $root = dirname(__DIR__);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $fineName = $file->getFilename();
            if ($file->isDir() ||
                strpos($fineName, '.') === 0 ||
                !is_writable($file)
            ) {
                continue;
            }
            $contents = file_get_contents($file);
            $contents = str_replace('__Vendor__', self::$values['vendor'], $contents);
            $contents = str_replace('__Package__', self::$values['package'], $contents);
            $contents = str_replace('__year__', date('Y'), $contents);
            $contents = str_replace('__name__', self::$values['name'], $contents);
            file_put_contents($file, $contents);
        }

        $skeletonFile = __DIR__ . '/Skeleton.php';
        copy($skeletonFile, $root."/src/".self::$values['package'].".php");
        unlink($skeletonFile);

        $skeletonTest = $root."/tests/SkeletonTest.php";
        copy($skeletonTest, $root."/tests/".self::$values['package']."Test.php");
        unlink($skeletonTest);

        // remove Installer
        unlink(__FILE__);

        $io = $event->getIO();
        $io->write("<info>The application values have been set</info>");
    }

    /**
     * Helper method to ask for values
     *
     * @param IOInterface $io
     * @param string $question
     * @param string $default
     *
     * @return string
     */
    private static function ask(IOInterface $io, $question, $default)
    {
        return $io->ask(
            sprintf("%s [<info>%s</info>]: ", $question, $default), $default
        );
    }

    /**
     * Helper method to convert camel to dashed
     *
     * @param string $name
     *
     * @return string
     */
    private static function camel2dashed($name)
    {
        // very specific!
        if ($name == "MinhD") {
            return "minhd";
        }

        return strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $name));
    }

}