<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Gdpr;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Gdpr\DTO\RequestGdprExportDTO;
use HiEvents\Services\Application\Handlers\Gdpr\RequestGdprDataExportHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class RequestGdprDataExportAction extends BaseAction
{
    public function __construct(private readonly RequestGdprDataExportHandler $handler)
    {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|max:255']);

        $this->handler->handle(new RequestGdprExportDTO(email: $request->input('email')));

        return $this->jsonResponse(data: [
            'message' => __('If we have data associated with this email, you will receive a download link shortly.'),
        ]);
    }
}
