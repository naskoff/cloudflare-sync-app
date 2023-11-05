<?php

namespace App\Command;

use App\Entity\Image;
use App\Repository\ImageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:download-cloudflare-images',
    description: 'Add a short description for your command',
)]
class DownloadCloudflareImagesCommand extends Command
{

    /** @var array<string, Image> */
    private array $images = [];

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ImageRepository $imageRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $storage = $this->kernel->getProjectDir().DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR;

        /** @var Image $image */
        foreach ($this->imageRepository->findBy([], []) as $image) {
            $url = sprintf(
                'https://imagedelivery.net/HKGs0qSz71mD4DZrAgz5Iw/%s/original',
                $image->getReference()
            );
            $io->info('Download image from: '. $url);
            file_put_contents($storage.$image->getReference(), file_get_contents($url));
        }

        return Command::SUCCESS;
    }
}
