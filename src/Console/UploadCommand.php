<?php

namespace Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;


class UploadCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('demo:upload')
            ->setDescription('Upload file to host.')
            ->addArgument(
                'filepath',
                InputArgument::REQUIRED,
                'Enter path to file you want to upload.'
            )
            ->addArgument(
                'host',
                InputArgument::OPTIONAL,
                'Enter host to connect.'
            );
    }

    public function testHostPortConnect($host, $port, $timeout)
    {
        $fp = @fsockopen ($host, $port, $errno, $errstr, $timeout);
        if ($fp) {
            fclose ($fp);
            return true;
        } else {
            return false;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$config = parse_ini_file('config.ini', true)) {
            throw new \Exception('<error>Error, config.ini file doesn\'t exist.</error>');
            return;
        } else {
            $output->writeln('<info>Loading config.ini file. Done.</info>');
        }

        $filepath = $input->getArgument('filepath');

        $rabbitMqHost = $input->getArgument('host')?:$config['mq']['host'];

        $port = $config['mq']['port'];
        $user = $config['mq']['user'];
        $pass = $config['mq']['pass'];
        $routing_key= $config['mq']['routing_key'];
        $maxFileSize = $config['mq']['max_file_size'];
        $fileTypes = $config['mq']['file_types'];

        if (file_exists($filepath)
            && filesize($filepath)<$maxFileSize
            && in_array(pathinfo($filepath)['extension'], $fileTypes, true)
            && $this->testHostPortConnect($rabbitMqHost, $port, 10)) {

            $file['ext'] = pathinfo($filepath)['extension'];
            $file['name'] = basename($filepath, '.' . $file['ext']);
            $file['session_id'] =  uniqid($file['name'], false);
            $file['data'] = file_get_contents($filepath);

            $connection = new AMQPConnection($rabbitMqHost, $port, $user, $pass);

            $channel = $connection->channel();

            $channel->exchange_declare($config['mq']['exchange'], 'direct', false, true, false);

            $msg = new AMQPMessage((string) json_encode($file), ['delivery_mode' => 2]);
            $channel->basic_publish($msg, $config['mq']['exchange'], $routing_key);

            $output->writeln(
                '<info>Sent file \'' . $filepath . '\' to host \'' . $rabbitMqHost . '\'.</info>'
            );

            $channel->close();
            $connection->close();

        } elseif (!file_exists($filepath)) {

            throw new \Exception('<error>Error, the file \'' . $filepath . '\' doesn\'t exist!</error>');

        } elseif (filesize($filepath)>$maxFileSize) {

            throw new \Exception('<error>Error, the file \'' . $filepath . '\' size can\'t be bigger then ' . round(($maxFileSize / pow(1024,2)), 5) . ' megabytes!</error>');

        } elseif (!in_array(pathinfo($filepath)['extension'], $fileTypes, true)) {

            throw new \Exception('<error>Error, wrong type of the file \'' . $filepath . '\'!</error>');

        } elseif (!$this->testHostPortConnect($rabbitMqHost, $port, 10)) {

            throw new \Exception('<error>Error, host \'' . $rabbitMqHost . '\' is not responding!</error>');

        }
    }
}