<?php

use App\Models\Announcement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NewPeriodsForAcademicPrograms extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Announcement::create([
            'academic_program_id' => 1,
            'from' => '2022-06-15',
            'to' => '2023-06-14'
        ]);

        Announcement::create([
            'academic_program_id' => 2,
            'from' => '2022-06-15',
            'to' => '2022-11-16'
        ]);

        // Announcement::create([
        //     'academic_program_id' => 3,
        //     'from' => '2022-02-01',
        //     'to' => '2023-11-12'
        // ]);

        Announcement::create([
            'academic_program_id' => 4,
            'from' => '2022-06-22',
            'to' => '2023-06-21'
        ]);

        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
