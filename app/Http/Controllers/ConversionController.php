<?php

namespace App\Http\Controllers;

use App\Actions\ConvertUploadedFile;
use App\Exceptions\ConversionException;
use App\Http\Requests\StoreConversionRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConversionController extends Controller
{
    public function store(StoreConversionRequest $request, ConvertUploadedFile $convert): JsonResponse
    {
        try {
            $result = $convert->handle(
                $request->file('file'),
                $request->string('from')->toString(),
                $request->string('to')->toString(),
            );
        } catch (ConversionException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function download(string $token, ConvertUploadedFile $convert): BinaryFileResponse
    {
        $file = $convert->find($token);

        abort_unless($file !== null, 404);

        return response()->download($file['path'], $file['name'], [
            'Content-Type' => $file['mime'],
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
