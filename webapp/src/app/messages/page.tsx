"use client";

import Link from "next/link";
import { FormEvent, Suspense, useEffect, useMemo, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import {
  ArrowLeft,
  ImageIcon,
  Loader2,
  MessageCircle,
  Paperclip,
  RefreshCw,
  Reply,
  Search,
  Send,
  SmilePlus,
  UserRound,
  Video,
  X,
} from "lucide-react";

import Header from "@/components/layout/Header";
import Footer from "@/components/layout/Footer";
import { getCurrentUser, getToken } from "@/lib/auth";
import {
  Conversation,
  MessageContact,
  MessageItem,
  MessageReaction,
  createConversation,
  fetchConversations,
  fetchMessageContacts,
  fetchMessages,
  getAssetUrl,
  reactToMessage,
  removeMessageReaction,
  sendConversationMessage,
} from "@/lib/marketplace";

const reactionOptions: Array<{
  value: MessageReaction["reaction"];
  label: string;
}> = [
  { value: "like", label: "J'aime" },
  { value: "love", label: "Love" },
  { value: "haha", label: "Haha" },
  { value: "wow", label: "Wow" },
  { value: "sad", label: "Triste" },
  { value: "angry", label: "Colere" },
];

function getErrorMessage(error: unknown, fallback: string) {
  return error instanceof Error ? error.message : fallback;
}

function getPhoto(user?: MessageContact | null) {
  return getAssetUrl(user?.photo_url || user?.photo);
}

function getMessageText(message?: MessageItem | null) {
  if (!message) return "Aucun message";
  if (message.message?.trim()) return message.message;
  if (message.media_type === "image") return "Image envoyee";
  if (message.media_type === "video") return "Video envoyee";
  return "Message";
}

function formatMessageDate(value?: string | null) {
  if (!value) return "";

  try {
    return new Intl.DateTimeFormat("fr-FR", {
      day: "2-digit",
      month: "short",
      hour: "2-digit",
      minute: "2-digit",
    }).format(new Date(value));
  } catch {
    return value;
  }
}

function getOtherUser(conversation: Conversation, currentUserId?: number | null) {
  if (conversation.client?.id && conversation.client.id !== currentUserId) {
    return conversation.client;
  }

  if (
    conversation.intervenant?.id &&
    conversation.intervenant.id !== currentUserId
  ) {
    return conversation.intervenant;
  }

  return conversation.intervenant || conversation.client || null;
}

function countReactions(message: MessageItem) {
  return (message.reactions || []).reduce<Record<string, number>>((acc, item) => {
    acc[item.reaction] = (acc[item.reaction] || 0) + 1;
    return acc;
  }, {});
}

function getMyReaction(message: MessageItem, currentUserId?: number | null) {
  return (message.reactions || []).find(
    (reaction) => reaction.user_id === currentUserId
  );
}

function MessagesContent() {
  const searchParams = useSearchParams();
  const messagesEndRef = useRef<HTMLDivElement | null>(null);
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const [currentUserId, setCurrentUserId] = useState<number | null>(null);
  const [contacts, setContacts] = useState<MessageContact[]>([]);
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [activeConversation, setActiveConversation] =
    useState<Conversation | null>(null);
  const [messages, setMessages] = useState<MessageItem[]>([]);
  const [query, setQuery] = useState("");
  const [message, setMessage] = useState("");
  const [media, setMedia] = useState<File | null>(null);
  const [replyTo, setReplyTo] = useState<MessageItem | null>(null);
  const [loading, setLoading] = useState(true);
  const [messagesLoading, setMessagesLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const initialUserId = searchParams.get("user_id");

  const filteredConversations = useMemo(() => {
    const searched = query.trim().toLowerCase();

    if (!searched) return conversations;

    return conversations.filter((conversation) => {
      const otherUser = getOtherUser(conversation, currentUserId);
      const lastMessage = conversation.messages?.[0];

      return `${otherUser?.name || ""} ${otherUser?.email || ""} ${getMessageText(
        lastMessage
      )}`
        .toLowerCase()
        .includes(searched);
    });
  }, [conversations, currentUserId, query]);

  const activeOtherUser = activeConversation
    ? getOtherUser(activeConversation, currentUserId)
    : null;

  async function loadInbox(options?: { preferredConversationId?: number }) {
    try {
      setError("");

      const [conversationItems, contactItems] = await Promise.all([
        fetchConversations(),
        fetchMessageContacts(),
      ]);

      setConversations(conversationItems);
      setContacts(contactItems);

      const preferredConversation =
        conversationItems.find(
          (item) => item.id === options?.preferredConversationId
        ) ||
        (activeConversation
          ? conversationItems.find((item) => item.id === activeConversation.id)
          : null) ||
        conversationItems[0] ||
        null;

      setActiveConversation(preferredConversation);

      if (preferredConversation) {
        await loadMessages(preferredConversation);
      } else {
        setMessages([]);
      }
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de charger la messagerie."));
    }
  }

  async function loadMessages(conversation: Conversation) {
    try {
      setMessagesLoading(true);
      setError("");
      setActiveConversation(conversation);
      setMessages(await fetchMessages(conversation.id));
    } catch (err) {
      setError(getErrorMessage(err, "Impossible de charger les messages."));
    } finally {
      setMessagesLoading(false);
    }
  }

  async function openConversationWithUser(userId: string | number) {
    try {
      setMessagesLoading(true);
      setError("");
      const conversation = await createConversation(userId);
      setActiveConversation(conversation);
      await loadInbox({ preferredConversationId: conversation.id });
    } catch (err) {
      setError(getErrorMessage(err, "Impossible d'ouvrir la conversation."));
    } finally {
      setMessagesLoading(false);
    }
  }

  useEffect(() => {
    const timer = window.setTimeout(async () => {
      if (!getToken()) {
        setLoading(false);
        setError("Veuillez vous connecter pour accéder à la messagerie.");
        return;
      }

      setCurrentUserId(getCurrentUser()?.id || null);

      try {
        setLoading(true);

        if (initialUserId) {
          await openConversationWithUser(initialUserId);
        } else {
          await loadInbox();
        }
      } finally {
        setLoading(false);
      }
    }, 0);

    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialUserId]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, activeConversation?.id]);

  async function handleSend(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!activeConversation) {
      setError("Sélectionnez une conversation avant d'envoyer un message.");
      return;
    }

    if (!message.trim() && !media) {
      setError("Écrivez un message ou ajoutez une image/vidéo.");
      return;
    }

    try {
      setSending(true);
      setError("");
      setSuccess("");

      await sendConversationMessage(activeConversation.id, {
        message,
        parent_id: replyTo?.id || null,
        media,
      });

      setMessage("");
      setMedia(null);
      setReplyTo(null);

      if (fileInputRef.current) {
        fileInputRef.current.value = "";
      }

      await loadMessages(activeConversation);
      await loadInbox({ preferredConversationId: activeConversation.id });
      setSuccess("Message envoyé.");
    } catch (err) {
      setError(getErrorMessage(err, "Impossible d'envoyer le message."));
    } finally {
      setSending(false);
    }
  }

  async function handleReaction(item: MessageItem, reaction: MessageReaction["reaction"]) {
    try {
      setError("");

      const myReaction = getMyReaction(item, currentUserId);

      if (myReaction?.reaction === reaction) {
        await removeMessageReaction(item.id);
      } else {
        await reactToMessage(item.id, reaction);
      }

      if (activeConversation) {
        await loadMessages(activeConversation);
      }
    } catch (err) {
      setError(getErrorMessage(err, "Impossible d'ajouter la réaction."));
    }
  }

  if (loading) {
    return (
      <>
        <Header />
        <main className="flex min-h-screen items-center justify-center bg-[#FFF7ED] px-4 pt-28 text-slate-950">
          <div className="inline-flex items-center gap-3 rounded-3xl bg-white px-6 py-5 text-sm font-black text-orange-700 shadow-sm">
            <Loader2 className="animate-spin" size={20} />
            Chargement de la messagerie...
          </div>
        </main>
      </>
    );
  }

  if (!getToken()) {
    return (
      <>
        <Header />
        <main className="min-h-screen bg-[#FFF7ED] px-4 pb-16 pt-36 text-slate-950">
          <div className="mx-auto max-w-2xl rounded-[2rem] bg-white p-8 text-center shadow-sm">
            <MessageCircle className="mx-auto mb-4 text-orange-600" size={42} />
            <h1 className="text-3xl font-black">Connexion nécessaire</h1>
            <p className="mx-auto mt-3 max-w-md text-sm font-semibold leading-7 text-slate-500">
              Connectez-vous pour consulter vos conversations et contacter un
              intervenant Gotfit.
            </p>
            <Link
              href={`/auth/login?redirect=${encodeURIComponent("/messages")}`}
              className="mt-6 inline-flex rounded-full bg-orange-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:bg-orange-700"
            >
              Se connecter
            </Link>
          </div>
        </main>
        <Footer />
      </>
    );
  }

  return (
    <>
      <Header />

      <main className="min-h-screen bg-[#FFF7ED] px-4 pb-28 pt-32 text-slate-950 lg:pb-16">
        <div className="mx-auto max-w-7xl">
          <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
              <Link
                href="/intervenants"
                className="mb-4 inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-xs font-black text-orange-700 shadow-sm transition hover:bg-orange-50"
              >
                <ArrowLeft size={16} />
                Retour aux intervenants
              </Link>
              <h1 className="text-4xl font-black tracking-tight sm:text-5xl">
                Messagerie Gotfit
              </h1>
              <p className="mt-3 max-w-2xl text-sm font-semibold leading-7 text-slate-600">
                Discutez avec vos coachs, envoyez des photos ou vidéos, répondez
                aux messages et ajoutez des réactions.
              </p>
            </div>

            <button
              type="button"
              onClick={() => loadInbox()}
              className="inline-flex items-center gap-2 rounded-full bg-white px-5 py-3 text-sm font-black text-orange-700 shadow-sm transition hover:bg-orange-50"
            >
              <RefreshCw size={17} />
              Actualiser
            </button>
          </div>

          {(error || success) && (
            <div
              className={`mb-5 rounded-2xl border px-5 py-4 text-sm font-bold ${
                error
                  ? "border-red-100 bg-red-50 text-red-700"
                  : "border-emerald-100 bg-emerald-50 text-emerald-700"
              }`}
            >
              {error || success}
            </div>
          )}

          <section className="grid overflow-hidden rounded-[2rem] border border-orange-100 bg-white shadow-[0_24px_90px_rgba(249,115,22,0.12)] lg:grid-cols-[380px_1fr]">
            <aside className="border-b border-orange-100 bg-[#FFFBF5] lg:border-b-0 lg:border-r">
              <div className="border-b border-orange-100 p-4">
                <div className="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-sm">
                  <Search className="text-slate-400" size={18} />
                  <input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Rechercher une conversation"
                    className="w-full bg-transparent text-sm font-semibold outline-none placeholder:text-slate-400"
                  />
                </div>
              </div>

              <div className="max-h-[240px] border-b border-orange-100 p-4 lg:max-h-[260px]">
                <p className="mb-3 text-xs font-black uppercase tracking-[0.16em] text-slate-400">
                  Nouveau message
                </p>
                <div className="flex gap-2 overflow-x-auto pb-1 lg:grid lg:max-h-[190px] lg:overflow-y-auto">
                  {contacts.map((contact) => {
                    const photo = getPhoto(contact);

                    return (
                      <button
                        type="button"
                        key={contact.id}
                        onClick={() => openConversationWithUser(contact.id)}
                        className="flex min-w-[150px] items-center gap-3 rounded-2xl bg-white p-3 text-left shadow-sm transition hover:bg-orange-50 lg:min-w-0"
                      >
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-orange-100 text-orange-700">
                          {photo ? (
                            <img
                              src={photo}
                              alt={contact.name}
                              className="h-full w-full object-cover"
                            />
                          ) : (
                            <UserRound size={19} />
                          )}
                        </span>
                        <span className="min-w-0">
                          <strong className="block truncate text-sm font-black">
                            {contact.name}
                          </strong>
                          <span className="block truncate text-xs font-bold text-slate-400">
                            {contact.role || "Utilisateur"}
                          </span>
                        </span>
                      </button>
                    );
                  })}
                </div>
              </div>

              <div className="max-h-[360px] overflow-y-auto p-3 lg:h-[540px] lg:max-h-none">
                {filteredConversations.length === 0 ? (
                  <div className="rounded-2xl bg-white p-5 text-center text-sm font-bold text-slate-500 shadow-sm">
                    Aucune conversation pour le moment.
                  </div>
                ) : (
                  filteredConversations.map((conversation) => {
                    const otherUser = getOtherUser(conversation, currentUserId);
                    const photo = getPhoto(otherUser);
                    const lastMessage = conversation.messages?.[0];
                    const active = conversation.id === activeConversation?.id;

                    return (
                      <button
                        type="button"
                        key={conversation.id}
                        onClick={() => loadMessages(conversation)}
                        className={`mb-2 flex w-full items-center gap-3 rounded-2xl p-3 text-left transition ${
                          active
                            ? "bg-orange-600 text-white shadow-lg shadow-orange-600/20"
                            : "bg-white text-slate-950 shadow-sm hover:bg-orange-50"
                        }`}
                      >
                        <span
                          className={`flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full ${
                            active ? "bg-white/20" : "bg-orange-100 text-orange-700"
                          }`}
                        >
                          {photo ? (
                            <img
                              src={photo}
                              alt={otherUser?.name || "Contact"}
                              className="h-full w-full object-cover"
                            />
                          ) : (
                            <UserRound size={22} />
                          )}
                        </span>
                        <span className="min-w-0 flex-1">
                          <span className="flex items-center justify-between gap-3">
                            <strong className="truncate text-sm font-black">
                              {otherUser?.name || "Conversation"}
                            </strong>
                            <small
                              className={`shrink-0 text-[10px] font-bold ${
                                active ? "text-white/75" : "text-slate-400"
                              }`}
                            >
                              {formatMessageDate(lastMessage?.created_at)}
                            </small>
                          </span>
                          <span
                            className={`mt-1 block truncate text-xs font-semibold ${
                              active ? "text-white/80" : "text-slate-500"
                            }`}
                          >
                            {getMessageText(lastMessage)}
                          </span>
                        </span>
                      </button>
                    );
                  })
                )}
              </div>
            </aside>

            <section className="flex min-h-[650px] flex-col">
              {activeConversation ? (
                <>
                  <div className="flex items-center justify-between gap-3 border-b border-orange-100 p-4">
                    <div className="flex items-center gap-3">
                      <span className="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full bg-orange-100 text-orange-700">
                        {getPhoto(activeOtherUser) ? (
                          <img
                            src={getPhoto(activeOtherUser)}
                            alt={activeOtherUser?.name || "Contact"}
                            className="h-full w-full object-cover"
                          />
                        ) : (
                          <UserRound size={22} />
                        )}
                      </span>
                      <div>
                        <strong className="block text-base font-black">
                          {activeOtherUser?.name || "Conversation"}
                        </strong>
                        <span className="text-xs font-bold text-slate-400">
                          Conversation #{activeConversation.id}
                        </span>
                      </div>
                    </div>

                    {messagesLoading && (
                      <Loader2 className="animate-spin text-orange-600" size={20} />
                    )}
                  </div>

                  <div className="flex-1 space-y-4 overflow-y-auto bg-[#FFF7ED] p-4 sm:p-6">
                    {messages.length === 0 ? (
                      <div className="flex h-full min-h-[360px] items-center justify-center">
                        <div className="max-w-sm rounded-[2rem] bg-white p-7 text-center shadow-sm">
                          <MessageCircle
                            className="mx-auto mb-4 text-orange-600"
                            size={42}
                          />
                          <strong className="text-xl font-black">
                            Aucun message
                          </strong>
                          <p className="mt-2 text-sm font-semibold leading-7 text-slate-500">
                            Envoyez le premier message pour démarrer la discussion.
                          </p>
                        </div>
                      </div>
                    ) : (
                      messages.map((item) => {
                        const mine = item.sender_id === currentUserId;
                        const mediaUrl = getAssetUrl(
                          item.media_full_url || item.media_url
                        );
                        const reactions = countReactions(item);
                        const myReaction = getMyReaction(item, currentUserId);

                        return (
                          <article
                            key={item.id}
                            className={`flex ${mine ? "justify-end" : "justify-start"}`}
                          >
                            <div
                              className={`max-w-[86%] rounded-[1.4rem] p-4 shadow-sm sm:max-w-[70%] ${
                                mine
                                  ? "rounded-br-md bg-orange-600 text-white"
                                  : "rounded-bl-md bg-white text-slate-950"
                              }`}
                            >
                              {item.parent && (
                                <div
                                  className={`mb-3 rounded-2xl border-l-4 px-3 py-2 text-xs font-semibold ${
                                    mine
                                      ? "border-white/50 bg-white/10 text-white/85"
                                      : "border-orange-300 bg-orange-50 text-slate-500"
                                  }`}
                                >
                                  <span className="block font-black">
                                    Réponse à {item.parent.sender?.name || "un message"}
                                  </span>
                                  <span className="line-clamp-2">
                                    {getMessageText(item.parent)}
                                  </span>
                                </div>
                              )}

                              {item.message && (
                                <p className="whitespace-pre-wrap text-sm font-semibold leading-7">
                                  {item.message}
                                </p>
                              )}

                              {mediaUrl && item.media_type === "image" && (
                                <a
                                  href={mediaUrl}
                                  target="_blank"
                                  className="mt-3 block overflow-hidden rounded-2xl"
                                >
                                  <img
                                    src={mediaUrl}
                                    alt="Media du message"
                                    className="max-h-[360px] w-full object-cover"
                                  />
                                </a>
                              )}

                              {mediaUrl && item.media_type === "video" && (
                                <video
                                  src={mediaUrl}
                                  controls
                                  className="mt-3 max-h-[360px] w-full rounded-2xl bg-slate-950"
                                />
                              )}

                              <div
                                className={`mt-3 flex flex-wrap items-center justify-between gap-2 text-[11px] font-bold ${
                                  mine ? "text-white/75" : "text-slate-400"
                                }`}
                              >
                                <span>
                                  {item.sender?.name || (mine ? "Moi" : "Contact")} ·{" "}
                                  {formatMessageDate(item.created_at)}
                                </span>
                                <button
                                  type="button"
                                  onClick={() => setReplyTo(item)}
                                  className={`inline-flex items-center gap-1 rounded-full px-2 py-1 ${
                                    mine
                                      ? "bg-white/10 text-white"
                                      : "bg-orange-50 text-orange-700"
                                  }`}
                                >
                                  <Reply size={13} />
                                  Répondre
                                </button>
                              </div>

                              <div className="mt-3 flex flex-wrap items-center gap-1">
                                {reactionOptions.map((reaction) => (
                                  <button
                                    type="button"
                                    key={reaction.value}
                                    onClick={() => handleReaction(item, reaction.value)}
                                    className={`rounded-full px-2 py-1 text-[11px] font-black transition ${
                                      myReaction?.reaction === reaction.value
                                        ? mine
                                          ? "bg-white text-orange-700"
                                          : "bg-orange-600 text-white"
                                        : mine
                                          ? "bg-white/10 text-white/80 hover:bg-white/20"
                                          : "bg-slate-50 text-slate-500 hover:bg-orange-50 hover:text-orange-700"
                                    }`}
                                  >
                                    {reaction.label}
                                    {reactions[reaction.value]
                                      ? ` ${reactions[reaction.value]}`
                                      : ""}
                                  </button>
                                ))}
                              </div>
                            </div>
                          </article>
                        );
                      })
                    )}
                    <div ref={messagesEndRef} />
                  </div>

                  <form
                    onSubmit={handleSend}
                    className="border-t border-orange-100 bg-white p-4"
                  >
                    {replyTo && (
                      <div className="mb-3 flex items-center justify-between gap-3 rounded-2xl bg-orange-50 px-4 py-3">
                        <div className="min-w-0 text-sm">
                          <strong className="block font-black text-orange-700">
                            Réponse à {replyTo.sender?.name || "un message"}
                          </strong>
                          <span className="block truncate font-semibold text-slate-500">
                            {getMessageText(replyTo)}
                          </span>
                        </div>
                        <button
                          type="button"
                          onClick={() => setReplyTo(null)}
                          className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-slate-500"
                        >
                          <X size={17} />
                        </button>
                      </div>
                    )}

                    {media && (
                      <div className="mb-3 flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm font-bold text-slate-600">
                        <span className="inline-flex min-w-0 items-center gap-2">
                          {media.type.startsWith("video/") ? (
                            <Video className="shrink-0 text-orange-600" size={18} />
                          ) : (
                            <ImageIcon
                              className="shrink-0 text-orange-600"
                              size={18}
                            />
                          )}
                          <span className="truncate">{media.name}</span>
                        </span>
                        <button
                          type="button"
                          onClick={() => {
                            setMedia(null);
                            if (fileInputRef.current) fileInputRef.current.value = "";
                          }}
                          className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-slate-500"
                        >
                          <X size={16} />
                        </button>
                      </div>
                    )}

                    <div className="flex items-end gap-2 rounded-[1.5rem] bg-slate-50 p-2">
                      <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/*,video/*"
                        onChange={(event) =>
                          setMedia(event.target.files?.[0] || null)
                        }
                        className="hidden"
                      />

                      <button
                        type="button"
                        onClick={() => fileInputRef.current?.click()}
                        className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white text-orange-700 shadow-sm transition hover:bg-orange-50"
                        aria-label="Ajouter un fichier"
                      >
                        <Paperclip size={20} />
                      </button>

                      <textarea
                        value={message}
                        onChange={(event) => setMessage(event.target.value)}
                        placeholder="Écrire un message..."
                        rows={1}
                        className="max-h-36 min-h-12 flex-1 resize-none bg-transparent px-2 py-3 text-sm font-semibold leading-6 outline-none placeholder:text-slate-400"
                      />

                      <button
                        type="submit"
                        disabled={sending}
                        className="flex h-12 shrink-0 items-center justify-center gap-2 rounded-2xl bg-orange-600 px-5 text-sm font-black text-white shadow-lg shadow-orange-600/20 transition hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-60"
                      >
                        {sending ? (
                          <Loader2 className="animate-spin" size={18} />
                        ) : (
                          <Send size={18} />
                        )}
                        <span className="hidden sm:inline">Envoyer</span>
                      </button>
                    </div>
                  </form>
                </>
              ) : (
                <div className="flex flex-1 items-center justify-center bg-[#FFF7ED] p-8">
                  <div className="max-w-md rounded-[2rem] bg-white p-8 text-center shadow-sm">
                    <SmilePlus className="mx-auto mb-4 text-orange-600" size={42} />
                    <h2 className="text-2xl font-black">
                      Sélectionnez une conversation
                    </h2>
                    <p className="mt-3 text-sm font-semibold leading-7 text-slate-500">
                      Choisissez un contact ou une conversation existante pour
                      afficher les messages ici.
                    </p>
                  </div>
                </div>
              )}
            </section>
          </section>
        </div>
      </main>

      <Footer />
    </>
  );
}

export default function MessagesPage() {
  return (
    <Suspense
      fallback={
        <>
          <Header />
          <main className="flex min-h-screen items-center justify-center bg-[#FFF7ED] px-4 pt-28 text-slate-950">
            <div className="inline-flex items-center gap-3 rounded-3xl bg-white px-6 py-5 text-sm font-black text-orange-700 shadow-sm">
              <Loader2 className="animate-spin" size={20} />
              Chargement de la messagerie...
            </div>
          </main>
        </>
      }
    >
      <MessagesContent />
    </Suspense>
  );
}
