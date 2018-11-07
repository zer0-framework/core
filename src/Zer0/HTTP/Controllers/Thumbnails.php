<?php

namespace Zer0\HTTP\Controllers;

use Zer0\FileStorage\Base;
use Zer0\FileStorage\Exceptions\OperationFailedException;
use Zer0\HTTP\AbstractController;
use Zer0\HTTP\Exceptions\BadRequest;
use Zer0\HTTP\Exceptions\InternalServerError;
use Zer0\HTTP\Exceptions\NotFound;

/**
 * Class Thumbnails
 * @package Zer0\HTTP\Controllers
 */
class Thumbnails extends AbstractController
{

    /**
     * @throws BadRequest
     * @throws NotFound
     */
    public function indexAction()
    {
        $format = strtolower($_SERVER['ROUTE_FORMAT']);
        $path = $_SERVER['ROUTE_PATH'];

        try {
            $split = explode('@', $format);
            $dimensions = $split[0];
            $colonSplit = explode(':', $split[1] ?? '');
            $method = $colonSplit[0] ?? '';
            $argumentsStr = $colonSplit[1] ?? '';
            if (!in_array($method, ['cut', 'pad'])) {
                $folder = explode('/', $path, 2)[0];
                if ($folder === 'logo') {
                    $method = 'pad';
                } else {
                    $method = 'cut';
                }
            }

            $xy = explode('x', $dimensions, 2);
            if (count($xy) < 2 || !ctype_digit($xy[0] . $xy[1])) {
                throw new BadRequest('Wrong dimensions.');
            }
            $x = (int)$xy[0];
            $y = (int)$xy[1];
            if ($x > 800 || $y > 800) {
                throw new BadRequest('Thumbnail cannot be larger than 800x800 by any of the dimensions.');
            }

            /**
             * @var Base $storage
             */
            $storage = $this->app->broker('FileStorage')->get();

            $tmp = sys_get_temp_dir() . '/' . sha1(microtime() . '.' . $format . '_' . $path);

            $url = $storage->getUrl('uploads', $path);

            $result = @copy($url, $tmp, stream_context_create(
                [
                    'http' => [
                        'follow_location' => false
                    ]
                ]
            ));

            if (!$result) {
                throw new NotFound;
            }
            $contentType = $result['ContentType'];
            $split = explode('/', $contentType);
            $imageFormat = $split[1] ?? '';

            $params = [
                'path' => $tmp,
                'xy' => $dimensions,
                'imageFormat' => $imageFormat,
            ];

            $pipeline = function ($cmd, $addParams = []) use ($params) {
                $tr = [];
                foreach (array_merge($params, $addParams) as $key => $value) {
                    $tr['%' . $key . '%'] = escapeshellarg($value);
                }
                $cmd = strtr($cmd, $tr) . ' 2>&1';
                exec($cmd, $output, $retCode);
                if ($retCode !== 0) {
                    throw new InternalServerError($cmd . "\n\n" . implode("\n", $output));
                }
                return $cmd;
            };

            if ($method === 'pad') {
                $arguments = explode(',', $argumentsStr ?? 'white');
                $pipeline(
                    'convert -define %imageFormat% %path%  -thumbnail %xy% -background %background% -gravity center -extent %xy% %path%',
                    [
                        'background' => $arguments[0],
                    ]
                );
            } elseif ($method === 'cut') {
                $pipeline('convert -define %imageFormat% %path%  -thumbnail %xy%^ -gravity center -extent %xy% %path%');
            }

            $this->http->header('Content-Type: ' . $contentType);
            readfile($tmp);
            $this->http->finishRequest();

            $storage->putFile($tmp, 'thumbnails', $format . '/' . $path, [
                'content-type' => $contentType
            ]);
        } catch (OperationFailedException $e) {
            throw new NotFound;
        } finally {
            if (isset($tmp) && is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
}
