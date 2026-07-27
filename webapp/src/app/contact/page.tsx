'use client';

import { FormEvent, useState } from 'react';
import {
  ArrowRight,
  CheckCircle2,
  Clock,
  Mail,
  MapPin,
  MessageCircle,
  Phone,
  Send,
} from 'lucide-react';
import Header from '@/components/layout/Header';
import Footer from '@/components/layout/Footer';
import { sendContactMessage } from '@/lib/contact';
import './contact.css';

type ContactFormState = {
  name: string;
  email: string;
  phone: string;
  subject: string;
  message: string;
};

const initialForm: ContactFormState = {
  name: '',
  email: '',
  phone: '',
  subject: '',
  message: '',
};

export default function ContactPage() {
  const [form, setForm] = useState<ContactFormState>(initialForm);
  const [loading, setLoading] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');
  const [errorMessage, setErrorMessage] = useState('');

  const updateField = (field: keyof ContactFormState, value: string) => {
    setForm((current) => ({
      ...current,
      [field]: value,
    }));
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    setLoading(true);
    setSuccessMessage('');
    setErrorMessage('');

    try {
      const response = await sendContactMessage({
        name: form.name.trim(),
        email: form.email.trim(),
        phone: form.phone.trim(),
        subject: form.subject.trim(),
        message: form.message.trim(),
      });

      if (response.success) {
        setSuccessMessage(
          response.message ||
            'Votre message a bien été envoyé. Nous vous répondrons rapidement.'
        );
        setForm(initialForm);
      } else {
        setErrorMessage(response.message || 'Impossible d’envoyer le message.');
      }
    } catch (error: unknown) {
      const message =
        error instanceof Error
          ? error.message
          : 'Impossible d’envoyer le message pour le moment. Veuillez réessayer plus tard.';

      setErrorMessage(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <Header />

      <main className="gotfit-contact">
        <section className="contact-hero">
          <div className="contact-hero__shape contact-hero__shape--one" />
          <div className="contact-hero__shape contact-hero__shape--two" />

          <div className="contact-hero__container">
            <div className="contact-hero__content">
              <span className="contact-eyebrow">
                <MessageCircle size={16} />
                Contact GotFit
              </span>

              <h1>
                Parlons de votre
                <span> objectif fitness.</span>
              </h1>

              <p>
                Une question, une demande de partenariat, un souci avec une
                réservation ou besoin d’accompagnement ? L’équipe GotFit est là
                pour vous répondre rapidement.
              </p>

              <div className="contact-hero__actions">
                <a href="#contact-form" className="contact-primary-link">
                  Envoyer un message
                  <ArrowRight size={18} />
                </a>

                <a
                  href="mailto:contact@gotfit.com"
                  className="contact-secondary-link"
                >
                  contact@gotfit.com
                </a>
              </div>
            </div>

            <div className="contact-hero__card">
              <div className="contact-logo-mark">GotFit</div>

              <div className="contact-hero__card-line" />

              <h2>Votre plateforme fitness</h2>

              <p>
                Connectez-vous aux bons coachs, intervenants et services pour
                avancer avec confiance.
              </p>

              <div className="contact-hero__features">
                <div>
                  <CheckCircle2 size={18} />
                  Réponse rapide
                </div>

                <div>
                  <CheckCircle2 size={18} />
                  Support utilisateurs
                </div>

                <div>
                  <CheckCircle2 size={18} />
                  Accompagnement GotFit
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="contact-main">
          <div className="contact-info-grid">
            <article className="contact-info-card">
              <div className="contact-info-card__icon">
                <Mail size={22} />
              </div>

              <div>
                <h3>Email</h3>
                <p>contact@gotfit.com</p>
              </div>
            </article>

            <article className="contact-info-card">
              <div className="contact-info-card__icon">
                <Phone size={22} />
              </div>

              <div>
                <h3>Téléphone</h3>
                <p>+33 00 00 000 00</p>
              </div>
            </article>

            <article className="contact-info-card">
              <div className="contact-info-card__icon">
                <MapPin size={22} />
              </div>

              <div>
                <h3>Localisation</h3>
                <p>France</p>
              </div>
            </article>

            <article className="contact-info-card">
              <div className="contact-info-card__icon">
                <Clock size={22} />
              </div>

              <div>
                <h3>Disponibilité</h3>
                <p>Lundi - Samedi, 8h à 18h</p>
              </div>
            </article>
          </div>

          <div className="contact-content">
            <div className="contact-left-panel">
              <span className="contact-section-badge">Support GotFit</span>

              <h2>Une question ? Écrivez-nous.</h2>

              <p>
                Remplissez le formulaire et notre équipe vous répondra dès que
                possible. Pour une demande liée à une réservation, indiquez
                clairement votre nom, votre email et le sujet de votre demande.
              </p>

              <div className="contact-left-panel__box">
                <h3>Conseil</h3>

                <p>
                  Plus votre message est précis, plus notre équipe pourra vous
                  répondre rapidement.
                </p>
              </div>
            </div>

            <form
              id="contact-form"
              className="contact-form"
              onSubmit={handleSubmit}
            >
              <div className="contact-form__header">
                <span>Formulaire de contact</span>
                <h2>Envoyer un message</h2>
              </div>

              {successMessage && (
                <div className="contact-alert contact-alert--success">
                  {successMessage}
                </div>
              )}

              {errorMessage && (
                <div className="contact-alert contact-alert--error">
                  {errorMessage}
                </div>
              )}

              <div className="contact-form__grid">
                <div className="contact-field">
                  <label htmlFor="name">Nom complet *</label>

                  <input
                    id="name"
                    type="text"
                    value={form.name}
                    onChange={(event) => updateField('name', event.target.value)}
                    placeholder="Votre nom"
                    autoComplete="name"
                    required
                  />
                </div>

                <div className="contact-field">
                  <label htmlFor="email">Adresse email *</label>

                  <input
                    id="email"
                    type="email"
                    value={form.email}
                    onChange={(event) =>
                      updateField('email', event.target.value)
                    }
                    placeholder="votre@email.com"
                    autoComplete="email"
                    required
                  />
                </div>
              </div>

              <div className="contact-form__grid">
                <div className="contact-field">
                  <label htmlFor="phone">Téléphone</label>

                  <input
                    id="phone"
                    type="tel"
                    value={form.phone}
                    onChange={(event) =>
                      updateField('phone', event.target.value)
                    }
                    placeholder="+33 ..."
                    autoComplete="tel"
                  />
                </div>

                <div className="contact-field">
                  <label htmlFor="subject">Sujet *</label>

                  <input
                    id="subject"
                    type="text"
                    value={form.subject}
                    onChange={(event) =>
                      updateField('subject', event.target.value)
                    }
                    placeholder="Ex : Réservation, partenariat..."
                    required
                  />
                </div>
              </div>

              <div className="contact-field">
                <label htmlFor="message">Message *</label>

                <textarea
                  id="message"
                  value={form.message}
                  onChange={(event) =>
                    updateField('message', event.target.value)
                  }
                  placeholder="Écrivez votre message..."
                  rows={7}
                  required
                />
              </div>

              <button
                type="submit"
                className="contact-submit"
                disabled={loading}
              >
                <Send size={18} />
                {loading ? 'Envoi en cours...' : 'Envoyer le message'}
              </button>
            </form>
          </div>
        </section>
      </main>

      <Footer />
    </>
  );
}
