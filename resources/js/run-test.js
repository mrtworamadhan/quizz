import http from 'k6/http';
import { sleep, check } from 'k6';

export const options = {
    // Skenario: 500 orang masuk serentak dan bertahan selama 1 menit
    vus: 500,        // 500 Virtual Users
    duration: '1m',  // Durasi pengujian 1 menit
};

export default function () {
    // Mensimulasikan peserta membuka halaman kuis/join
    const res = http.get('https://quizz.evencapt.com/join');
    
    // Pastikan server membalas dengan status 200 (OK), bukan 500 (Error) atau 504 (Timeout)
    check(res, {
        'status is 200': (r) => r.status === 200,
    });

    // Simulasi jeda baca peserta 3 detik sebelum melakukan aksi lain
    sleep(3); 
}