<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Document;

class ImageCompression implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $document;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($document)
    {
        $this->document = $document;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $document = $this->document;
        $user = ___HQ($document['owner_id']);
        $path = getUserDocumentPath($user);
        $pathNew = $path . DIRECTORY_SEPARATOR. $document['file_name'];
        \Spatie\LaravelImageOptimizer\Facades\ImageOptimizer::optimize($pathNew);
        logger('----------> 2.Path_bool:: <-=-==-====', [file_exists($pathNew)]);
        logger('----------> 2.Path:: <-=-==-===='. $pathNew);
        return;
       // $filePath = storage_path($document['filePath']);
//        $filePath = $document['filePath'];
//        logger('----------> Path_bool:: <-=-==-====', [file_exists($filePath)]);
//        logger('----------> Path:: <-=-==-===='. $filePath);
//        logger('====>', [$document]);
//        logger('OWNER====>', [$document->owner_id]);
//        return;
        $uploader = new \App\Http\Libraries\Uploader();
        $uploader->compression($pathNew);
    }
}
