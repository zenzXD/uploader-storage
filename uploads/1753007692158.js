import axios from 'axios'
import FormData from 'form-data'

async function transcribe(buffer) {
    if (!buffer || !Buffer.isBuffer(buffer)) throw new Error('audio bufer diperlukan');

    const form = new FormData();
    form.append('file', buffer, `${Date.now()}_zennxz.mp3`);

    const headers = {
        ...form.getHeaders(),
        'Content-Length': await new Promise((resolve, reject) => {
            form.getLength((err, length) => {
                if (err) reject(err);
                else resolve(length);
            });
        }),
    };

    const { data } = await axios.post(
        'https://audio-transcription-api.752web.workers.dev/api/transcribe',
        form,
        { headers }
    );

    return data.transcription || null; 
}

let handler = async (m) => {
    if (!m.quoted || !m.quoted.mimetype?.includes('audio')) {
        return m.reply('Reply audio yang mau diubah ke teks');
    }

    try {
        m.reply('wettt');
        const buffer = await m.quoted.download();
        const text = await transcribe(buffer);

        if (!text) {
            return m.reply('Transkripnya kosong bree, coba audio lain');
        }

        m.reply(`hasil : \n\n${text}`);
    } catch (e) {
        m.reply(`Eror kak : ${e.message}`);
    }
};

handler.help = ['audio2text']
handler.tags = ['tools']
handler.command = ['audio2text']

export default handler