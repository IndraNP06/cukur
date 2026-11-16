<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('detail_transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksi')->onDelete('cascade');
            $table->enum('tipe_item', ['Layanan', 'Barang']);
            $table->unsignedBigInteger('item_id'); // Polymorphic
            $table->string('nama_item_snapshot');
            $table->integer('qty');
            $table->decimal('harga_satuan_snapshot', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('detail_transaksi');
    }
};
