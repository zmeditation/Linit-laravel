<?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    class CreateSlidersTable extends Migration
    {
        /**
         * Run the migrations.
         *
         * @return void
         */
        public function up()
        {
            Schema::create('sliders', function (Blueprint $table) {
                $table->id();
                $table->string('img');
                $table->string('titre');
                $table->string('soustitre')->nullable();
                $table->text('detail')->nullable();
                $table->boolean('active')->default(true);
                $table->integer('rang')->nullable();
                $table->timestamps();
            });
        }

        /**
         * Reverse the migrations.
         *
         * @return void
         */
        public function down()
        {
            Schema::dropIfExists('sliders');
        }
    }