<?php
declare(strict_types=1);

namespace Zer0\Mailer\Tasks;

use Zer0\App;
use Zer0\Mailer\Base;

/**
 * Class SendMail
 * @package Zer0\Mailer\Tasks
 */
final class SendMail extends \Zer0\Queue\TaskAbstract
{
    /**
     * @var string
     */
    protected $to;

    /**
     * @var string
     */
    protected $subject;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var mixed|null
     */
    protected $additional_headers;

    /**
     * @var null|string
     */
    protected $additional_parameters;

    /**
     * SendMail constructor.
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param mixed $additional_headers
     * @param string|null $additional_parameters
     */
    public function __construct(
        string $to,
        string $subject,
        string $message,
        $additional_headers = null,
        string $additional_parameters = null
    ) {
        $this->to = $to;
        $this->subject = $subject;
        $this->message = $message;
        $this->additional_headers = $additional_headers;
        $this->additional_parameters = $additional_parameters;
    }

    /**
     *
     */
    public function execute(): void
    {
        /**
         * @var Base $mailer
         */
        $mailer = App::instance()->broker('Mailer')->get();

        $mailer->send(
            $this->to,
            $this->subject,
            $this->message,
            $this->additional_headers,
            $this->additional_parameters
        );

        $this->complete();
    }
}
