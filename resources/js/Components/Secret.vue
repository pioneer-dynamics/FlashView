<script setup>
    import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
    import {encryption} from '../encryption';
    import { computed, ref } from 'vue';
    import TextAreaInput from '@/Components/TextAreaInput.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import TextInput from '@/Components/TextInput.vue';
    import InputError from '@/Components/InputError.vue';
    import FlatFormSection from '@/Components/FlatFormSection.vue';
    import FlatActionSection from '@/Components/FlatActionSection.vue';
    import Faq from '@/Components/Faq.vue';
    import InputLabel from '@/Components/InputLabel.vue';
    import SelectInput from '@/Components/SelectInput.vue';
    import CodeBlock from '@/Components/CodeBlock.vue';
    import Alert from './Alert.vue';
    import { router } from '@inertiajs/vue3'

    const letsDoAnotherOne = () => {
        form.message = '';
        form.expires_in = usePage().props.config.secrets.expiry ;
        other.password = null;
        router.reload();
    }

    const props = defineProps({
        secret: {
            type: String,
            default: null,
        },
        decryptUrl: {
            type: String,
            default: null,
        }
    })

    const passwordInput = ref(null);

    const form = useForm({
        message: props.secret ? 'This isn’t the actual message—it’s just a placeholder. To view the message, please click the button below.' : '',
        expires_in: usePage().props.config.secrets.expiry ,
    });

    const decryptForm = useForm({
        terms: false,
    })

    const other = useForm({
        password: null,
    });

    const decryptionSuccess = ref(false)

    const messageClass = computed(() => {
        if(props.secret) {
            if(!usePage().props.jetstream.flash?.secret?.message || decryptionSuccess.value === false) {
                return 'mt-1 block w-full blur-sm';
            }
            else {
                return 'mt-1 block w-full';
            }
        }
        else {
            return 'mt-1 block w-full';
        }
    })

    const getErrorMessage = (error) => {
        switch(error.code) {
            case 429: return 'Wow! Too many requests too fast, pace yourselves. Please try again in a minute. We will soon introduce user accounts with a higher rate limit.';
            default: return error.message;
        }
    }

    const encryptData = () => {

        if(form.message == null || form.message.length == 0)
        {
            form.setError('message', 'Please enter a message.');
            return ;
        }

        const e = new encryption();
        
        e.encryptMessage(form.message, other.password).then((data) => {
            form.transform((formdata) => ({
                ...formdata,
                message: data.secret
            }))
                .post(route('secret.store'), {
                    onSuccess: () => {
                        if(usePage().props.jetstream.flash?.error)
                        {
                            form.setError('message', getErrorMessage(usePage().props.jetstream.flash.error));
                            
                            return;
                        }
                        if(data.passphrase != other.password)
                        {
                            other.password = data.passphrase;
                        }
                    },
                    onError: () => form.setError('message', e.message),
                });
            
        }).
        catch((e) => {
            other.setError('password', e.message);
        });
    }

    const passwordPlaceholder = computed(() => {
        if(props.secret == null)
            return 'Enter a passsword, or leave blank to auto generate a password for you.';
        else
            return 'Enter your password to decrypt the message.'
    })

    const showPrivacyOptions = ref(false)

    const decryptData = () => {
        const e = new encryption();
        decryptForm.get(props.decryptUrl, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if(!usePage().props.jetstream.flash?.error && usePage().props.jetstream.flash?.error?.code != 404)
                {
                    const secretMessage = usePage().props.jetstream.flash.secret.message;
                    
                    const passphrase = other.password;

                    e.decryptMessage(secretMessage, passphrase)
                        .then((data) => {
                            form.message = data;
                            decryptionSuccess.value = true;
                        })
                        .catch((error) => {
                            form.setError('message', error);
                        });
                }
            },
            onError: () => {
                form.setError('message', 'Could not get your message. Either the password was wrong, or the message is already expired, or the message was already retrieved. You have no more attempts.');
            }
        })

        
    }
</script>
<style>
    .underline-svg {
      position: absolute;
      bottom: -12px; /* Adjust for spacing */
      left: 0;
      width: 100%;
      height: 20px; /* Adjust SVG height */
      fill: none;
      /* stroke: #3b82f6; Tailwind's blue-500 */
      @apply stroke-gamboge-500;
      stroke-width: 8; /* Double thickness */
      stroke-dasharray: 400; /* Total length of the path */
      stroke-dashoffset: 400; /* Initially hidden */
      animation: draw 1s cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }

    @keyframes draw {
      to {
        stroke-dashoffset: 0;
      }
    }
</style>
<template>
    <FlatFormSection>
        <template #title>
            FlashView
        </template>

        <template #form>
            <div class="col-span-12">
                <Alert v-if="props.secret != null" type="Warning" hide-title>
                    <div class="mb-2">
                        You can attempt to retrieve this message 
                        <span class="relative inline-block">
                            ONLY ONCE
                            <svg class="underline-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 20">
                                <path d="M0 15 Q150 -10 300 15" />
                            </svg>
                        </span>.
                    </div>
                    <ol class="space-y-1 list-decimal list-inside">
                        <li>Wrong password will result in the message being deleted.</li>
                        <li>Correct password will show the message, and the message will be deleted.</li>
                        <li>Message will be deleted after the expiration time.</li>
                    </ol>
                </Alert>
                <Alert hide-title v-if="$page.props.jetstream.flash.error?.code == 404" type="Error">
                    This message has expired or has already been retrieved.
                </Alert>
            </div>
            <div class="col-span-12" v-if="$page.props.jetstream.flash?.secret?.url">
                <Alert hide-title type="Success">
                    Please share the link and password separately to the recipient. The message can be retrieved only once and only with both the link and the password. If you wish to prematurely delete the message, you may visit the link and enter any random password and click retrieve.
                </Alert>
            </div>
            <div class="col-span-12">
                <CodeBlock v-if="$page.props.jetstream.flash?.secret?.url" :value="$page.props.jetstream.flash?.secret?.url" class="break-words"/>
                <TextAreaInput v-else :autofocus="props.secret == null" id="message" rows="7" v-model="form.message" type="text" :class="messageClass" placeholder="Your secret message..." />
                <InputError :message="form.errors.message" class="mt-2" />
            </div>
            <div class="col-span-12">
                <div class="flex flex-wrap sm:flex-nowrap gap-2 sm:space-y-0 sm:space-y-0">
                    <div class="w-full" v-if="$page.props.jetstream.flash?.secret?.message == undefined">
                        <CodeBlock v-if="$page.props.jetstream.flash?.secret?.url" :value="other.password"/>
                        <TextInput v-else id="password" :autofocus="props.secret != null" ref="passwordInput" v-model="other.password" type="text" class="mt-1 block w-full" :placeholder="passwordPlaceholder" />
                        <InputError :message="other.errors.password" class="mt-2" />
                    </div>
                    <div v-if="!$page.props.jetstream.flash?.secret?.url && props.secret == null">
                        <SelectInput id="expires_in" v-model="form.expires_in" class="mt-1 block w-full" :options="$page.props.config.secrets.expiry_options" />
                        <InputError :message="other.errors.expires_in" class="mt-2" />
                    </div>
                </div>
            </div>
        </template>

        <template #actions>
            <span v-if="props.secret == null">
                <PrimaryButton @click.prevent="letsDoAnotherOne" v-if="$page.props.jetstream.flash?.secret?.url" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Let's do another one
                </PrimaryButton>
                <PrimaryButton @click.prevent="encryptData" v-else :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Generate link
                </PrimaryButton>
            </span>
            <span v-else>
                <PrimaryButton @click.prevent="decryptData" v-if="!$page.props.jetstream.flash?.secret?.message" :class="{ 'opacity-25': decryptForm.processing || (other.password?.length == 0 || other.password == null) }" :disabled="decryptForm.processing || (other.password?.length == 0 || other.password == null)">
                    Retrieve Message
                </PrimaryButton>
                <Link :href="route('welcome')" v-else class="inline-flex items-center px-4 py-2 bg-gamboge-800 dark:bg-gamboge-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gamboge-800 uppercase tracking-widest hover:bg-gamboge-700 dark:hover:bg-white focus:bg-gamboge-700 dark:focus:bg-white active:bg-gamboge-900 dark:active:bg-gamboge-300 focus:outline-none focus:ring-2 focus:ring-gamboge-500 focus:ring-offset-2 dark:focus:ring-offset-gamboge-800 disabled:opacity-50 transition ease-in-out duration-150">
                    Send a new secret link
                </Link>
            </span>
        </template>
    </FlatFormSection>
    <FlatActionSection v-if="!$page.props.jetstream.flash?.secret?.url" class="pt-4">
        <h2 class="text-4xl font-extrabold dark:text-white">F.A.Q.</h2>

        <Faq :question="'How safe is sharing a secret with ' + $page.props.config.app.name + ' ?'">
            The secret message is encrypted on your browser. The passphrase and the plaintext secret message is never sent to {{ $page.props.config.app.name }}. Only the encrypted message is sent to the server. So we have no means to decrypt the message. For the same reason, a hacker who gains complete access to all our systems will still not be able to decode the secret message.
        </Faq>
        <Faq question="How do you compare with other providers, like onetimesecret.com?">
            Most other providers like onetimesecret.com send the secret and password as plaintext to the server and the server does the encryption. Though the good people at onetimesecret.com do not misuse this, a man in the middle could still grab the secret message. {{ $page.props.config.app.name }} encrypts the message on your browser before sending it to the server. This way we, or a man in the middle, do not know what the secret is or what the passphrase to decrypt it is.
        </Faq>
        <Faq question="How secure is the encryption?">
            The encryption is based on AES-256-CBC which is one for most secure encryption algorithms available to mankind. With the existing technology it will take millions of years for someone to crack the encryption.
        </Faq>
        <Faq question="Why don't you add the passphrase to the link like other providers?">
            While it is convinient for the end user to encode the passphrase into the link, it would also mean that our server receives the passphrase and so does a man in the middle. By not including the passphrase in the link, we have completely cut off ourselves and any man in the middle from being able to retrieve the secret message.
        </Faq>
        <Faq question="How do I delete the secret message?">
            The secret message is deleted when it reaches the expiry set while generating the secret link, or on the first attempt to retrieve the message - whichever comes first. If you wish to manually delete the secret before it is retrieved, you could just visit the same link and give any random password and press retrieve to delete the message.
        </Faq>
        <Faq question="How long will the secret be available?">
            The secret message is deleted when it reaches the expiry set while generating the secret link, or on the first attempt to retrieve the message - whichever comes first.
        </Faq>
        <Faq question="What would be some of the usecases?">
            <ul class="list-disc list-inside">
                <li>For sending passwords, or share your Netflix credentials with familiy.</li>
                <li>To confess to your secret crush.</li>
                <li>Tell your kids about your grandfather's secret treasure stash.</li>
                <li>Literally anything...</li>
            </ul>
        </Faq>
        <Faq question="How do you avoid harrasment and other illegal use of the system?">
            We log the IP address and the time at which a link is generated and retrieved. This log is kept for {{$page.props.config.secrets.prune_after}} days after which they are permanently deleted as well. If a legal authority produces a court order to request details about a message, we will provide them with the IP address from which and the time at which the link was generated and accessed as long as it has not yet been pruned by the system.
        </Faq>
        <Faq question="Isn't that a privacy concern?">
            All secret messages are stored encrypted and encryption occurs at the browser before it is sent to us. So the content of the message is still secure and of no use to anyone except the recipient. We will not be able to retrieve the message content even if we wanted to. We will only share the metadata (IP address and time it was created, and the IP address and time it was retrieved) with legal authorities and only if they request it with a proper court order that relates to an investigation that is related to illegal use like harrasment or terrorism. In all other cases no metadata will be shared.
        </Faq>
        <Faq question="How are the metadata stored?">
            The IP Addresses is stored encrypted use AES-256-CBC algorithm with an "application key". This key is not stored in the database and is only stored on our application server. Hence a hack into our database will not reveal the metadata. The time at which a message was created or retrieved is not stored encrypted.
        </Faq>
        <Faq question="How can legal authorities contact you with a court order?">
            If you are a legal authority, you can email us at <a class="underline text-gamboge-200" :href="'mailto:' + $page.props.config.support.legal ">{{ $page.props.config.support.legal }}</a>. The request should have the retrieval URL for the message, a scanned copy of the notorised court order, and the reason for the request. To speed up the process (that is to help us validate the authority of the court order) we recommend that the email be sent from the registed domain name for the court or the legal authority and is signed using a valid digital signature.
        </Faq>
        <Faq question="Why is it rate limited?">
            We rate limit the number of times an anonymous user can generate links to avoid abuse. We will soon introduce user accounts which will allown you to generate more links per minute.
        </Faq>
    </FlatActionSection>
</template>