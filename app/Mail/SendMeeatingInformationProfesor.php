<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendMeeatingInformationProfesor extends Mailable
{
    use Queueable, SerializesModels;

    
    public $Interview;
    public $Student;
    public $Profesor;
    public $Room;
    public $academic_program;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($Interview,$Profesor, $academic_program, $Room,$Student)
    {
     
        $this->Interview= $Interview;
        $this->Profesor=$Profesor;
        $this->Room=$Room;
        $this->Student=$Student;
        $this->academic_program=$academic_program;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('rtic.ambiental@uaslp.mx', 'DETALLES DE ENTREVISTA')->view('Correos.MeetingProfesor')->subject('Información de entrevista');
    }
}
