<?php

namespace App\Command;

use App\Entity\Image;
use App\Repository\ImageRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:delete-cloudflare-images',
    description: 'Add a short description for your command',
)]
class DeleteCloudflareImagesCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly HttpClientInterface $cloudflareClient,
    ) {
        parent::__construct();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        foreach ($this->imageRepository->findBy(['isUsed' => false], [], 500) as $image) {
            $this->deleteImage($image);
        }

        return Command::SUCCESS;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function deleteImage(Image $image): void
    {
        $url = sprintf('images/v1/%s', $image->getReference());

        $response = $this->cloudflareClient->request(Request::METHOD_DELETE, $url);

        if (200 === $response->getStatusCode()) {
            $this->io->success('Successfully removed image with id: '.$image->getReference());
        } else {
            $this->io->warning(sprintf('Image not deleted. (%s)', $response->getContent(false)));
        }
    }
}
