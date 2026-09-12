require("dotenv").config();
const express = require("express");
const cors = require("cors");
const nodemailer = require("nodemailer");
const path = require("path");

const app = express();
const PORT = process.env.PORT || 5000;

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Serve static landing page files
app.use(express.static(path.join(__dirname)));

// Configure Nodemailer Transporter
const transporter = nodemailer.createTransport({
  host: process.env.SMTP_HOST || "smtp.zoho.com",
  port: Number(process.env.SMTP_PORT) || 465,
  secure: true,
  auth: {
    user: process.env.SMTP_USER || "shawnkebel@taptae.org",
    pass: process.env.SMTP_PASS || "Yoshi1988*",
  },
  tls: {
    rejectUnauthorized: false,
  },
});

// Verify transporter connection on startup
transporter.verify((error) => {
  if (error) {
    console.error("❌ Email Transporter verification failed:", error.message);
  } else {
    console.log("✅ Email Transporter verified & ready to send emails!");
  }
});

// POST /api/inquiry - Real Email Dispatch Endpoint
app.post("/api/inquiry", async (req, res) => {
  const { name, email, projectType, message } = req.body;

  if (!name || !email || !projectType || !message) {
    return res.status(400).json({
      status: "error",
      message: "All fields (name, email, projectType, message) are required.",
    });
  }

  const receiver = process.env.RECEIVER_EMAIL || "skebel@kbldesigners.com";

  const mailOptions = {
    from: `"KBL Designers™ Inquiry" <${process.env.SMTP_USER || "shawnkebel@taptae.org"}>`,
    replyTo: email,
    to: receiver,
    subject: `🚀 New Project Inquiry: ${projectType} from ${name}`,
    html: `
      <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #0d1421; color: #f8fafc; border-radius: 12px; border: 1px solid #38bdf8;">
        <h2 style="color: #38bdf8; margin-top: 0; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">
          New Project Inquiry - KBL Designers™
        </h2>
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
          <tr>
            <td style="padding: 8px 0; color: #94a3b8; font-weight: bold; width: 130px;">Client Name:</td>
            <td style="padding: 8px 0; color: #ffffff;">${name}</td>
          </tr>
          <tr>
            <td style="padding: 8px 0; color: #94a3b8; font-weight: bold;">Client Email:</td>
            <td style="padding: 8px 0; color: #38bdf8;"><a href="mailto:${email}" style="color: #38bdf8;">${email}</a></td>
          </tr>
          <tr>
            <td style="padding: 8px 0; color: #94a3b8; font-weight: bold;">Project Focus:</td>
            <td style="padding: 8px 0; color: #10b981; font-weight: bold;">${projectType}</td>
          </tr>
        </table>
        <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 8px; border: 1px solid rgba(255,255,255,0.08);">
          <div style="color: #94a3b8; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 8px;">Project Details & Requirements:</div>
          <div style="color: #f8fafc; white-space: pre-wrap; font-size: 14px; line-height: 1.6;">${message}</div>
        </div>
        <div style="margin-top: 20px; font-size: 11px; color: #64748b; text-align: center;">
          Sent from KBL Designers™ Landing Page Engine
        </div>
      </div>
    `,
  };

  try {
    const info = await transporter.sendMail(mailOptions);
    console.log(
      `✅ Email sent successfully to ${receiver}! Message ID: ${info.messageId}`,
    );
    return res.json({
      status: "success",
      message: `Inquiry sent successfully to ${receiver}!`,
    });
  } catch (error) {
    console.error("❌ Failed to send email:", error);
    return res.status(500).json({
      status: "error",
      message: `Failed to send email: ${error.message}`,
    });
  }
});

app.listen(PORT, () => {
  console.log(
    `🌐 KBL Designers™ Landing Page Server running on http://localhost:${PORT}`,
  );
});
