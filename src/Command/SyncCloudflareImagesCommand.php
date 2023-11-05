<?php

namespace App\Command;

use App\Entity\Image;
use App\Entity\Log;
use App\Repository\ImageRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:sync-cloudflare-images',
    description: 'Add a short description for your command',
)]
class SyncCloudflareImagesCommand extends Command
{
    private SymfonyStyle $io;

    /** @var array<string, Image> */
    private array $currentState = [];

    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $cloudflareClient,
    ) {
        parent::__construct();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        foreach ($this->imageRepository->findAll() as $image) {
            $this->currentState[$image->getReference()] = $image;
        }

        $continuationToken = null;
        do {

            $this->io->info('Process requests with continuation_token: '. ($continuationToken ?? 'EMPTY'));
            /**
             * @var array{
             *     errors: string[],
             *     messages: string[],
             *     result: array{
             *          continuation_token: string,
             *          images: array{
             *              filename: string,
             *              id: string,
             *              meta: array
             *          },
             *          requireSignedURLs: boolean,
             *          uploaded: string,
             *          variants: string[]
             *     },
             *     success: boolean
             * } $response
             */
            $response = $this->getImages($continuationToken);

            if (true !== $response['success']) {
                foreach ($response['errors'] as $error) {
                    $this->io->error(sprintf('[%s] %s', $error['code'], $error['message']));
                }

                break;
            }

            $this->processImages($response['result']['images']);
        } while (null !== $continuationToken = $response['result']['continuation_token']);

        return Command::SUCCESS;
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    private function getImages(?string $continuationToken): array
    {
        $request = $this->cloudflareClient->request(Request::METHOD_GET, 'images/v2', [
            'query' => ['continuation_token' => $continuationToken],
        ]);

        $data = $request->toArray(false);

        $log = new Log(
            request: $request->getInfo('debug'),
            response: $request->getContent(false),
            requestAt: new DateTime(),
            statusCode: $request->getStatusCode()
        );

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $data;
    }

    /**
     * @param array{
     *     filename: string,
     *     id: string,
     *     meta: array,
     *     requireSignedURLs: boolean,
     *     uploaded: string,
     *     variants: string[]
     * }[] $images
     *
     * @throws Exception
     */
    private function processImages(array $images): void
    {
        $this->io->info('Process images ...');

        foreach ($images as $data) {
            if (isset($this->currentState[$data['id']])) {
                $this->io->info(sprintf('Image with id: %s (%s) already saved.', $data['id'], $data['filename']));
                continue;
            }

            $image = new Image(
                reference: $data['id'],
                filename: $data['filename'],
                metadata: $data['meta'],
                uploadedAt: new DateTime($data['uploaded']),
                context: $data
            );

            $this->entityManager->persist($image);
            $this->entityManager->flush();

            $this->currentState[$image->getReference()] = $image;

            $this->io->info(sprintf('Save image with id: %s (%s) already saved.', $data['id'], $data['filename']));
        }

        $this->io->info('DONE!');
    }
}
