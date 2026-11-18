<?php

use Livewire\Component;
use App\Models\Participant;
use Illuminate\Support\Facades\Session;

new class extends Component
{
    public $name;
    public $class_name;

    // Aturan validasi agar peserta tidak mengisi form kosong
    protected $rules = [
        'name' => 'required|min:3|max:255',
        'class_name' => 'required|max:255',
    ];

    protected $messages = [
        'name.required' => 'Nama wajib diisi, Bro.',
        'name.min' => 'Nama minimal 3 karakter.',
        'class_name.required' => 'Asal kelas wajib diisi.',
    ];

    public function mount()
    {
        // Jika peserta sudah pernah login dan sesinya masih ada, 
        // langsung lempar saja ke ruang kuis agar tidak dobel daftar.
        if (Session::has('participant_uuid')) {
            return redirect()->route('quiz.room'); 
        }
    }

    public function submitForm()
    {
        // 1. Jalankan validasi
        $this->validate();

        // 2. Simpan peserta baru ke database
        // UUID akan otomatis terbuat berkat fungsi boot() di Model Participant yang kita buat sebelumnya
        $participant = Participant::create([
            'name' => $this->name,
            'class_name' => $this->class_name,
        ]);

        // 3. Simpan UUID ke session sebagai tanda pengenal
        Session::put('participant_uuid', $participant->uuid);

        // 4. Arahkan peserta ke halaman kuis
        return redirect()->route('quiz.room');
    }
};
?>

<div class="min-h-screen bg-gray-100 flex flex-col justify-center items-center p-4 font-sans">
    
    <!-- Kotak Form dengan border atas khas tema -->
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl p-8 border-t-8 border-orange-500 relative overflow-hidden">
        
        <!-- HEADER & LOGO AREA -->
        <div class="text-center mb-8">
            
            <div class="w-24 h-24 mx-auto mb-4 bg-gray-50 border-2 border-dashed border-orange-300 rounded-full flex flex-col items-center justify-center text-orange-400">
                <img 
                    src="{{ asset('images/LOGO BAWASLU HASS.png') }}" 
                    alt="Logo Bawaslu" 
                    class="h-20 md:h-24 object-contain"
                >
            </div>
            
            <h1 class="text-3xl font-black text-gray-800 mb-1">generaSi Sigap</h1>
            <p class="text-sm text-orange-600 font-bold italic mb-2">"Bawaslu Siap, Generasi Sigap"</p>
            <p class="text-gray-500 text-sm mt-4">Silakan lengkapi data diri untuk bergabung ke dalam kuis.</p>
        </div>

        <form wire:submit.prevent="submitForm">
            
            <div class="mb-5">
                <label for="name" class="block text-sm font-bold text-gray-700 mb-1">Nama Lengkap</label>
                <input 
                    type="text" 
                    id="name" 
                    wire:model="name" 
                    class="w-full px-4 py-3 rounded-lg bg-gray-50 border border-gray-300 focus:bg-white focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition-all duration-200"
                    placeholder="Masukkan nama lengkap..."
                >
                @error('name') <span class="text-red-500 text-xs mt-1 font-semibold">{{ $message }}</span> @enderror
            </div>

            <div class="mb-8">
                <label for="class_name" class="block text-sm font-bold text-gray-700 mb-1">Asal Kelas</label>
                <input 
                    type="text" 
                    id="class_name" 
                    wire:model="class_name" 
                    class="w-full px-4 py-3 rounded-lg bg-gray-50 border border-gray-300 focus:bg-white focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition-all duration-200"
                    placeholder="Contoh: XII IPA 1"
                >
                @error('class_name') <span class="text-red-500 text-xs mt-1 font-semibold">{{ $message }}</span> @enderror
            </div>

            <button 
                type="submit" 
                class="w-full bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-black py-4 px-4 rounded-xl shadow-lg hover:shadow-orange-500/30 transition-all duration-200 transform hover:-translate-y-1"
            >
                <span wire:loading.remove>MASUK KE RUANG KUIS</span>
                <span wire:loading>Memproses Data...</span>
            </button>

        </form>
    </div>
    
    <div class="mt-8 text-center text-gray-400 text-xs">
        &copy; {{ date('Y') }} Sistem Kuis Interaktif by evencapt.com - part of salakatech.com
    </div>
</div>