<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Gdpr;

use HiEvents\Exceptions\InvalidGdprExportTokenException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Gdpr\DownloadGdprDataExportHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DownloadGdprDataExportAction extends BaseAction
{
    public function __construct(private readonly DownloadGdprDataExportHandler $handler)
    {
    }

    public function __invoke(Request $request, string $token): JsonResponse
    {
        try {
            $data = $this->handler->handle($token);
        } catch (InvalidGdprExportTokenException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonResponse($data)->withHeaders([
            'Content-Disposition' => 'attachment; filename="data-export.json"',
        ]);
    }
}
