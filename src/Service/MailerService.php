<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailerService
{
    public function __construct(
        private string $webappUrl,
        private MailerInterface $mailerInterface,
        private Environment $twig
    ) {
    }

    public function sendPasswordForgottenMail(User $user): void
    {
        $actionUrl = $this->webappUrl . '/password?token=' . $user->getPasswordCreationToken();

        $htmlContent = $this->twig->render('emails/password_forgotten.html.twig', [
            'user'  => $user,
            'actionUrl' => $actionUrl,
        ]);

        $this->sendMail(
            to: $user->getEmail(),
            subject: 'Mot de passe oublié',
            message: $htmlContent
        );
    }


    private function sendMail(
        string $to,
        string $subject,
        string $message
    ): void {
        $email = (new Email())
            ->from('contact@sikomobility.com')
            ->to($to)
            ->subject($subject)
            ->html($message);

        $this->mailerInterface->send($email);
    }
}
