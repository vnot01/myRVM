<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('password');
            $table->string('avatar')->nullable()->after('google_id');
            $table->string('phone_number')->nullable()->unique()->after('avatar');
            $table->enum('citizenship', ['WNI', 'WNA'])->nullable()->after('phone_number');
            $table->enum('identity_type', ['KTP', 'Pasport'])->nullable()->after('citizenship');
            $table->string('identity_number')->nullable()->unique()->after('identity_type');
            $table->integer('points')->unsigned()->default(0)->after('identity_number');
            $table->enum('role', ['Admin', 'Operator', 'User'])->default('User')->after('points');
            $table->boolean('is_guest')->default(false)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google_id',
                'avatar',
                'phone_number',
                'citizenship',
                'identity_type',
                'identity_number',
                'points',
                'role',
                'is_guest',
            ]);
        });
    }
};
