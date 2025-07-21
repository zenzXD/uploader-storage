
/*
* Nama Fitur : Musicscope
* Type : Plugin Esm
* Author : ZenzzXD
*/


import axios from 'axios'
import FormData from 'form-data'

class MusicScope {
    constructor() {
        this.api_url = 'https://ziqiangao-musicscopegen.hf.space/gradio_api'
        this.file_url = 'https://ziqiangao-musicscopegen.hf.space/gradio_api/file='
    }

    generateSession() {
        return Math.random().toString(36).substring(2)
    }

    async upload(buffer, filename) {
        const upload_id = this.generateSession()
        const form = new FormData()
        form.append('files', buffer, filename)
        const { data } = await axios.post(`${this.api_url}/upload?upload_id=${upload_id}`, form, {
            headers: {
                ...form.getHeaders()
            }
        })
        return {
            orig_name: filename,
            path: data[0],
            url: `${this.file_url}${data[0]}`
        }
    }

    async process({ title = 'MusicScope', artist = 'ZenzzXD', audio, image } = {}) {
        if (!audio || !Buffer.isBuffer(audio)) throw new Error('Audio buffer is required')
        if (!image || !Buffer.isBuffer(image)) throw new Error('Image buffer is required')

        const audio_url = await this.upload(audio, `${Date.now()}zennxzz.mp3`)
        const image_url = await this.upload(image, `${Date.now()}zennxzz.jpg`)
        const session_hash = this.generateSession()
        await axios.post(`${this.api_url}/queue/join?`, {
            data: [
                {
                    path: audio_url.path,
                    url: audio_url.url,
                    orig_name: audio_url.orig_name,
                    size: audio.length,
                    mime_type: 'audio/mpeg',
                    meta: {
                        _type: 'gradio.FileData'
                    }
                },
                null,
                'Output',
                30,
                1280,
                720,
                1024,
                {
                    path: image_url.path,
                    url: image_url.url,
                    orig_name: image_url.orig_name,
                    size: image.length,
                    mime_type: 'image/jpeg',
                    meta: {
                        _type: 'gradio.FileData'
                    }
                },
                title,
                artist
            ],
            event_data: null,
            fn_index: 0,
            trigger_id: 26,
            session_hash: session_hash
        })

        const { data } = await axios.get(`${this.api_url}/queue/data?session_hash=${session_hash}`)
        let result
        const lines = data.split('\n\n')
        for (const line of lines) {
            if (line.startsWith('data:')) {
                const d = JSON.parse(line.substring(6))
                if (d.msg === 'process_completed') result = d.output.data[0].video.url
            }
        }

        if (!result) throw new Error('no video url returned')
        return result
    }
}

const handler = async (m, { conn, text }) => {
    if (!text) return m.reply(`contoh : .musicscope Judul|Artis|URL_Gambar (replay audio)`)

    let [title, artist, imgUrl] = text.split('|').map(v => v.trim())
    if (!title || !artist || !imgUrl) return m.reply(`Contoh: .musicscope judul|artis|url_gambar (reply audio)`)

    let q = m.quoted ? m.quoted : m
    let mime = (q.msg || q).mimetype || ''
    if (!/^audio/.test(mime)) return m.reply('reply audio nya bgg')

    try {
        m.reply('wettt')
        let audioBuffer = await q.download()
        let imageBuffer = (await axios.get(imgUrl, { responseType: 'arraybuffer' })).data
        const ms = new MusicScope()
        const videoUrl = await ms.process({ title, artist, audio: audioBuffer, image: imageBuffer })
        if (!videoUrl) throw new Error('aduhh gagal mendapatkan video :(')
        await conn.sendFile(m.chat, videoUrl, 'zennxz.mp4', '', m)
    } catch (err) {
        m.reply(`Eror kak : ${err.message}`)
    }
}

handler.help = ['musicscope <judul>|<artis>|<url_gambar>']
handler.tags = ['maker']
handler.command = ['musicscope']

export default handler