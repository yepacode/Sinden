<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clientes_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users');
            $table->string('nombre_archivo');
            $table->integer('total_filas')->default(0);
            $table->integer('creados')->default(0);
            $table->integer('actualizados')->default(0);
            $table->integer('errores')->default(0);
            $table->string('estado', 30)->default('procesando');
            $table->json('detalle_log')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('clientes_imports');
    }
};
