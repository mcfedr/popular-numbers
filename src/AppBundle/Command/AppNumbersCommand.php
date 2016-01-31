<?php

namespace AppBundle\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Response;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppNumbersCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:numbers')
            ->setDescription('show some numbers')
            ->addArgument('file', InputArgument::REQUIRED, 'Argument description');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fp = fopen($input->getArgument('file'), 'w');
        Promise\each_limit($this->search(), 50, function($result) use ($output, $fp) {
            $output->writeln("{$result[0]} - $result[1]");
            fputcsv($fp, $result);
        })->wait();
        fclose($fp);
        $output->writeln('Finished');
    }

    private function search()
    {
        // https://oeis.org/search?q=1010&sort=&language=english&go=Search
        $client = new Client([
            'base_uri' => 'https://oeis.org',
            'cookies' => true
        ]);
        for ($i = 0;$i < 1000000;$i++) {
            $a = function($number) use ($client) {
                return $client->getAsync('/search', [
                    'query' => [
                        'q' => $number,
                        'language' => 'english',
                        'go' => 'Search'
                    ]
                ])->then(function(Response $response) use ($number) {
                    $content = $response->getBody()->getContents();
                    preg_match('/of (\d+) results|Found (\d+) results/', $content, $matches);
                    $found = $matches[1] ? $matches[1] : ($matches[2] ? $matches[2] : 0);
                    return [$number, $found];
                });
            };
            yield $a($i);
        }
    }
}
