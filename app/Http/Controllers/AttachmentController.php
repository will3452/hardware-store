<?php

namespace App\Http\Controllers;

use App\Models\BusinessDocument;
use App\Models\DocumentAttachment;
use App\Services\StoreAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function upload(Request $request, string $store, string $document, StoreAccess $access): RedirectResponse
    {
        $request->validate(['attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']]);
        $file = $request->file('attachment');
        abort_unless($file instanceof UploadedFile, 422);
        $path = null;
        try {
            DB::transaction(function () use ($request, $document, $access, $file, &$path): void {
                $store = $access->lock($request->user());
                $doc = BusinessDocument::findOrFail($document);
                $module = match ($doc->kind) {
                    'expense', 'expense_reversal' => 'expenses', 'receipt', 'receipt_reversal' => 'purchasing', 'sale', 'return' => 'sales', 'collection', 'collection_reversal', 'opening_receivable', 'opening_receivable_reversal' => 'credit', default => 'inventory'
                };
                $access->module($module, true);
                StoreAccess::check(DocumentAttachment::where('business_document_id', $doc->id)->count() < 10, 'A document may have at most ten attachments.');
                $path = $file->store('attachments/'.$store->id, 'local');
                if ($path === false) {
                    throw new \RuntimeException('The file could not be stored. Please retry.');
                }
                $name = preg_replace('/[^\pL\pN ._()-]/u', '_', $file->getClientOriginalName());
                DocumentAttachment::create(['business_document_id' => $doc->id, 'user_id' => $request->user()->id, 'path' => $path, 'name' => mb_substr($name ?: 'attachment', 0, 200), 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
                StoreAccess::audit($request->user(), $store->id, 'document.attachment_added', $doc->id);
            });
        } catch (\Throwable $error) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return back()->with('status', 'Private attachment saved.');
    }

    public function download(string $store, string $attachment): StreamedResponse
    {
        $file = DocumentAttachment::findOrFail($attachment);

        return Storage::disk('local')->download($file->path, $file->name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
