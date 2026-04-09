<?php

/*
|--------------------------------------------------------------------------
| Document Signatories — DIU Student Services
|--------------------------------------------------------------------------
| Maps each department to its authorised signatory for generated documents.
| Update name, title, and signature_image when the office-holder changes.
| signature_image paths are relative to storage/app/private/signatures/.
| Set signature_image to null to render the signature line blank (for
| physical signing of printed copies, if ever required).
*/

return [

    'registrar' => [
        'name'            => 'Prof. Dr. Md. Abdur Rahman',
        'title'           => 'Registrar',
        'department'      => 'Office of the Registrar',
        'institution'     => 'Daffodil International University',
        'signature_image' => null,   // e.g. 'registrar_signature.png'
        'phone'           => '+880-2-9138234',
        'email'           => 'registrar@diu.edu.bd',
    ],

    'exam-controller' => [
        'name'            => 'Dr. Sharmin Sultana',
        'title'           => 'Controller of Examinations',
        'department'      => "Exam Controller's Office",
        'institution'     => 'Daffodil International University',
        'signature_image' => null,
        'phone'           => '+880-2-9138234',
        'email'           => 'exam@diu.edu.bd',
    ],

    'career-services' => [
        'name'            => 'Mr. Md. Kamrul Hasan',
        'title'           => 'Director, Career Services',
        'department'      => 'Career Development Centre',
        'institution'     => 'Daffodil International University',
        'signature_image' => null,
        'phone'           => '+880-2-9138234',
        'email'           => 'career@diu.edu.bd',
    ],

    'student-affairs' => [
        'name'            => 'Prof. Dr. Farhana Begum',
        'title'           => 'Dean, Student Affairs',
        'department'      => 'Office of Student Affairs',
        'institution'     => 'Daffodil International University',
        'signature_image' => null,
        'phone'           => '+880-2-9138234',
        'email'           => 'studentaffairs@diu.edu.bd',
    ],

];
