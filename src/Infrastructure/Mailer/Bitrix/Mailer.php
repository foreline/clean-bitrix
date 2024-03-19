<?php
    declare(strict_types=1);
    
    namespace Infrastructure\Mailer\Bitrix;

    use Bitrix\Main\Config\Option;
    use Bitrix\Main\Mail\Mail;
    use Exception;
    use Infrastructure\Mailer\MailerInterface;
    use Infrastructure\Mailer\MessageInterface;

    /**
     *
     */
    class Mailer implements MailerInterface
    {
        /**
         * @param MessageInterface $message
         * @return void
         * @throws Exception
         */
        public function send(MessageInterface $message): void
        {
            $body = str_replace(PHP_EOL, '<br />' . PHP_EOL, $message->getBody());
            
            $siteName = Option::get('main', 'site_name');
            $mailFrom = Option::get('main', 'email_from');
            
            $data = [
                'TO'    => $message->getTo(),
                'SUBJECT'   => $message->getSubject(),
                'BODY'      => $body,
                'HEADER'    => [
                    'From'  => $siteName . ' <' . $mailFrom . '>',
                    'Reply-To'  => $mailFrom,
                ],
                'CONTENT_TYPE'  => 'html',
                'CHARSET'       => 'UTF-8',
            ];
            
            if ( !Mail::send($data) ) {
                throw new Exception('Ошибка при отправке почтового сообщения');
            }
        }
    }