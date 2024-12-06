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
    import InputHelp from '@/Components/InputHelp.vue';
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

    const decryptForm = useForm({})

    const other = useForm({
        password: null,
    });

    const messageClass = computed(() => {
        if(props.secret && !usePage().props.jetstream.flash?.secret?.message)
            return 'mt-1 block w-full blur-sm';
        else
            return 'mt-1 block w-full';
    })

    const passwordVisible = ref('password');

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
                    if(data.passphrase != other.password)
                    {
                        other.password = data.passphrase;
                    }
                }
            })
            
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
                if(!usePage().props.jetstream.flash?.error && usePage().props.jetstream.flash?.error != 404)
                {
                    const secretMessage = usePage().props.jetstream.flash.secret.message;
                    
                    const passphrase = other.password;

                    e.decryptMessage(secretMessage, passphrase)
                        .then((data) => {
                            form.message = data;
                        })
                        .catch((e) => {
                            form.setError('message', e.message);
                        });
                }
            },
            onError: () => {
                form.setError('message', 'Could not get your message. Either the password was wrong, or the message is already expired, or the message was already retrieved. You have no more attempts.');
            }
        })

        
    }
</script>
<template>
    <FlatFormSection>
        <template #title>
            FlashView
        </template>

        <template #form>
            <div class="col-span-12">
                <Alert v-if="props.secret != null" type="Warning" hide-title>
                    <div class="mb-2">
                        You can attempt to retrieve this message ONLY ONCE.
                    </div>
                    <ol class="space-y-1 list-decimal list-inside">
                        <li>Wrong password will result in the message being deleted.</li>
                        <li>Correct password will show the message, and the message will be deleted.</li>
                        <li>Message will be deleted after the expiration time.</li>
                    </ol>
                </Alert>
                <Alert hide-title v-if="$page.props.jetstream.flash.error == 404" type="Error">
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
                    <div class="w-full">
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
                <li>Tell your kids about your grandfathers secret treasure stash.</li>
                <li>Literally anything...</li>
            </ul>
        </Faq>
    </FlatActionSection>
</template>