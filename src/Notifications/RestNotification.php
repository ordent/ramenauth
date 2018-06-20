<?php

namespace Ordent\RamenAuth\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class RestNotification extends Notification implements ShouldQueue{
    use Queueable;

    protected $via = ['mail'];
    protected $subject = '';
    protected $template = '';
    protected $value = [];
    protected $type = 'forgot';
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($via = 'email', $type = 'forgot', $value)
    {
        $this->via = $this->resolveVia($via);
        $this->subject = $this->resolveSubject($type, $via);
        $this->template = $this->resolveTemplate($type, $via);
        $this->value = $value;
    }

    private function resolveVia($via){
        
        switch ($via) {
            case 'email':
                $result = ['mail'];
                break;
            default:
                $result = [$via];
                break;
        }
        return $result;
    }

    private function resolveSubject($type, $via){
        $result = "";
        switch ($type) {
            case 'forgot':
                $result = "Forget Password";
                break;
            case 'verify':
                $result = "Verify Account";
                break;
        }
        return $result;
    }

    private function resolveTemplate($type, $via){
        if($via == 'email'){
            $via = 'mail';
        }
        $result = "ramenauth::".$type."-".$via;
        return $result;
    }
    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return $this->via;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {

        if($this->type == 'verify'){
            $email = base64_encode($this->value['users']->email);
            $url = '/api/accounts/'.$email.'/verify_accounts/'.$this->value['code'].'/html' ;
            return (new MailMessage)
                        ->subject($this->subject)
                        ->action('Verify Mail', ['url' => $url])
                        ->markdown($this->template, ['value' => $this->value, 'url' => $url]);
        }else{
            $email = base64_encode($this->value['users']->email);
            $url = '/api/accounts/'.$email.'/forgot_password/'.$this->value['code'];
            return (new MailMessage)
                        ->subject($this->subject)
                        ->action('Forgot Password', ['url' => $url])
                        ->markdown($this->template, ['value' => $this->value, 'url' => $url]);
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
