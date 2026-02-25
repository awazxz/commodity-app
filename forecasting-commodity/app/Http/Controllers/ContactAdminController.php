<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
// use App\Models\ContactRequest; // Aktifkan jika Anda membuat database/model

class ContactAdminController extends Controller
{
    /**
     * Menampilkan halaman Hubungi Admin (Sisi Publik)
     */
    public function index()
    {
        return view('contact-admin');
    }

    /**
     * Menangani form submit dari halaman Hubungi Admin (Sisi Publik)
     */
    public function submit(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'subject' => 'required|string',
            'message' => 'required|string|min:10',
        ]);

        // LOGIC: Anda bisa menyimpan ke database atau mengirim email langsung
        // Contoh simpan ke database (pastikan sudah buat migrasinya):
        // ContactRequest::create($request->all());

        return back()->with('success', 'Pesan Anda telah terkirim ke tim IT BPS Riau. Mohon tunggu balasan melalui email.');
    }

    // =====================================================
    // ADMIN SIDE METHODS (Membutuhkan Login Admin)
    // =====================================================

    /**
     * Menampilkan daftar permintaan bantuan di Dashboard Admin
     */
    public function adminIndex()
    {
        // $contacts = ContactRequest::latest()->paginate(10);
        // return view('admin.contacts.index', compact('contacts'));
        
        return "Halaman daftar kontak admin sedang dalam pengembangan.";
    }

    /**
     * Menampilkan detail permintaan bantuan
     */
    public function adminShow($id)
    {
        // $contact = ContactRequest::findOrFail($id);
        // return view('admin.contacts.show', compact('contact'));
    }

    /**
     * Update status (misal: dari 'pending' ke 'selesai')
     */
    public function updateStatus(Request $request, $id)
    {
        // $contact = ContactRequest::findOrFail($id);
        // $contact->update(['status' => $request->status]);
        
        return back()->with('success', 'Status berhasil diperbarui.');
    }

    /**
     * Menghapus log permintaan bantuan
     */
    public function destroy($id)
    {
        // $contact = ContactRequest::findOrFail($id);
        // $contact->delete();
        
        return back()->with('success', 'Data berhasil dihapus.');
    }
}