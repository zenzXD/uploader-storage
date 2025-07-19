import fetch from 'node-fetch'
import FormData from 'form-data'

const qr = {
  readFromBuffer: async (imageBuffer) => {
    if (!Buffer.isBuffer(imageBuffer)) throw Error(`Invalid buffer input`)

    const body = new FormData()
    body.append("file", imageBuffer, { filename: "file.png", contentType: "image/png" })

    const headers = {
      ...body.getHeaders()
    }

    const response = await fetch("http://api.qrserver.com/v1/read-qr-code/", {
      body,
      headers,
      method: "POST"
    })

    if (!response.ok) throw Error(`${response.statusText} ${response.status}\n${await response.text()}`)

    const json = await response.json()
    const data = json?.[0]?.symbol?.[0]?.data
    if (!data) throw Error(`Request OK tapi hasil anomali : \n${JSON.stringify(json, null, 2)}`)
    return data
  },

  create: async (text) => {
    if (typeof text !== "string" || text.length === 0) throw Error(`Input text invalid atau kosong`)
    const response = await fetch(`http://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(text)}&size=1000x1000&margin=50&qzone=1`)
    const arrayBuffer = await response.arrayBuffer()
    const buffer = Buffer.from(arrayBuffer)
    return buffer
  }
}

const handler = async (m, { conn, text, command }) => {
  if (command === 'qrcode') {
    if (!text) return m.reply(
      `Contoh penggunaan:\n.qrcode\n> untuk membuat qr, contoh: .qrcode hello world\n.readqr\n> untuk membaca qr, replay gambar lalu ketik: .readqr`
    )
    try {
      const qrBuffer = await qr.create(text)
      conn.sendFile(m.chat, qrBuffer, 'qrcode.png', 'qr berhasil dibuwat', m)
    } catch (err) {
      m.reply(`Eror kak : \n\n${err.message}`)
    }
  }

  if (command === 'readqr') {
    if (!m.quoted || !m.quoted.mimetype?.startsWith('image/')) return m.reply(
      `contoh penggunaan:\n.qrcode\n> untuk membuat qr, contoh: .qrcode hello world\n.readqr\n> untuk membaca qr, replay gambar lalu ketik: .readqr`
    )
    try {
      const img = await m.quoted.download()
      const result = await qr.readFromBuffer(img)
      m.reply(`nih : \n\n${result}`)
    } catch (err) {
      m.reply(`Eror kak : \n\n${err.message}`)
    }
  }
}

handler.help = ['qrcode <text>', 'readqr <replay gambar>']
handler.tags = ['tools']
handler.command = ['qrcode', 'readqr']

export default handler