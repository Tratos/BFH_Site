<?php

namespace App\Http\Controllers;

use function App\can;
use App;
use App\FriendRequest;
use App\GameHeroes;
use App\GameStats;
use App\User;
use GuzzleHttp;
use App\UserFriend;
use App\UserSignature;
use App\UserDiscord;
use App\UserRevive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;

class ProfileController extends Controller
{
    // User profiles
    // A users own profile
    public function lists()
    {
        $friends = Auth::user()->friends();
        return view('profile.lists', compact('friends'));
    }

    public function addSignature()
    {
        $validation = Validator::make(Input::all(), [
            'image' => 'image|mimes:jpg,jpeg,png,gif',
        ]);
        if ($validation->fails())
            return redirect()->back()->with('error', 'You must only upload PNG, JPG, and GIF files!');

        if( ! Input::hasFile('image'))
            return redirect()->back()->with('error', 'You need to have a image chosen.');

        $file = Input::file('image');
        $fileName = $file->getClientOriginalName();
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = str_random(48) . ".$ext";
        $file->move(public_path() . '/images/signatures/', $newFileName);

        if(UserSignature::where('user_id', Auth::id())->exists())
            UserSignature::where('user_id', Auth::id())->first()->update([
                'image' => '/images/signatures/' . $newFileName,
            ]);
        else
            UserSignature::create([
                'user_id' => Auth::id(),
                'image' => '/images/signatures/' . $newFileName,
            ]);

        return redirect()->back()->with('success', 'Your signature got added!');
    }

    public function addAvatar()
    {
        $validation = Validator::make(Input::all(), [
            'image' => 'image|mimes:jpg,jpeg,png,gif',
        ]);
        if ($validation->fails())
            return redirect()->back()->with('error', 'You must only upload PNG, JPG, and GIF files!');

        if( ! Input::hasFile('image'))
            return redirect()->back()->with('error', 'You need to have a image chosen.');

        $newFileName = str_random(48) . ".png";
        $file = Image::make(Input::file('image'))->resize(250, 250)->encode('png', 75);
        $file->save(public_path('images/avatars/'. $newFileName));

        Auth::user()->update(['avatar' => '/images/avatars/' . $newFileName]);

        return redirect()->back()->with('success', 'Your avatar got added!');
    }

    public function addDescription()
    {
        if(Input::get('description') == '')
            return redirect()->back()->with('error', 'The description cannot be empty!');
        $description = str_replace(['&lt;script&gt;'], '', Input::get('description'));
        $description = str_replace(['&lt;/script&gt;'], '', $description);

        Auth::user()->update([
            'description' => $description
        ]);

        return redirect()->back()->with('success', 'Your description got updated!');
    }

    public function details($username)
    {
        if( ! User::where('username', $username)->exists())
            abort(404);

        $user = User::where('username', $username)->first();
        return view('profile.details', compact('user'));
    }

    public function answerFriendRequest()
    {
        Auth::user()->friendRequestAnswer(Input::get('sender'), Input::get('answer'));

        return redirect()->back()->with('success', Input::get('answer') == 'accepted' ? 'You accepted the friend request!' : 'You declined the friend request!');
    }

    public function removeFriend($user_id)
    {
        if(UserFriend::where('user_id', $user_id)->where('friend_id', Auth::id())->exists())
        {
            UserFriend::where('user_id', $user_id)->where('friend_id', Auth::id())->first()->delete();
            return redirect()->back()->with('success', 'Your friend was removed!');
        } elseif (UserFriend::where('friend_id', $user_id)->where('user_id', Auth::id())->exists())
        {
            UserFriend::where('friend_id', $user_id)->where('user_id', Auth::id())->first()->delete();
            return redirect()->back()->with('success', 'Your friend was removed!');
        } else
            return redirect()->back()->with('error', 'This user is not your friend!');
    }

    public function addFriend($user)
    {
        // Check if a friend request already exists.
        // If it does, add friend.
        if (FriendRequest::where('receiver', Auth::id())->where('sender', $user)->exists())
        {
            Auth::user()->addFriend($user);
            return redirect()->back()->with('success', User::find($user)->username . ' was added to your friend list');
        }
        else
        // If not, send one.
        {
            Auth::user()->sendFriendRequest($user);
            return redirect()->back()->with('success', 'A friend request was sent!');
        }
    }

    public function linkDiscord()
    {
        $provider = new \Discord\OAuth\Discord([
            'clientId'                => '332411803877376001',    // The client ID assigned to you by the provider
            'clientSecret'            => 'vYUQusAzlOyzm6Fzr5bhwkEoJ6wND_Ic',   // The client password assigned to you by the provider
            'redirectUri'             =>  route('profile.linkDiscord'),
        ]);

        // If we don't have an authorization code then get one
        if (!isset($_GET['code'])) {

            // Fetch the authorization URL from the provider; this returns the
            // urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $authorizationUrl = $provider->getAuthorizationUrl();

            // Get the state generated for you and store it to the session.
            //$_SESSION['oauth2state'] = $provider->getState();

            // Redirect the user to the authorization URL.
            header('Location: ' . $authorizationUrl);
            exit;

        } else {
            $oldID = 0;

            if(Auth::user()->discordLink != null)
            {
                $oldID = Auth::user()->discordLink->discord_id;
            }
            

            $token = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code'],
            ]);

            // Get the user object.
            $user = $provider->getResourceOwner($token);

            if(UserDiscord::where('user_id', Auth::id())->exists())
                UserDiscord::where('user_id', Auth::id())->first()->update([
                    'discord_id' => $user->id,
                    'discord_name' => $user->username,
                    'discord_email' => $user->email,
                    'discord_discriminator' => $user->discriminator,
                ]);
            else
                UserDiscord::create([
                    'user_id' => Auth::id(),
                    'discord_id' => $user->id,
                    'discord_name' => $user->username,
                    'discord_email' => $user->email,
                    'discord_discriminator' => $user->discriminator,
                ]);

            // Tell bot to refresh role of user
            // 329078443687936001 = guildID of HeroesAwaken Discord
            $client = new \GuzzleHttp\Client();
            $res = $client->get('https://bot.heroesawaken.com/api/refresh/329078443687936001/' . $user->id);

            if ($oldID != 0) {
                $res = $client->get('https://bot.heroesawaken.com/api/refresh/329078443687936001/' . $oldID);
            }

            return redirect()->route('profile.lists')->with('success', 'We linked your discord account!');
        }
    }

    public function linkRevive()
    {
        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId' => 'heroesawaken'.(App::environment('local') ? '_local' : ''),
            'clientSecret' => 'fx05a3q8vv86nie0pwiglcb7p9yfh27m',
            'urlAuthorize' => 'https://battlelog.co/oauth/authorize/',
            'urlAccessToken' => 'https://battlelog.co/oauth/token/',
            'urlResourceOwnerDetails' => 'https://battlelog.co/oauth/tokeninfo/'
        ]);

        // If we don't have an authorization code then get one
        if (!isset($_GET['code'])) {
            header('Location: ' . $provider->getAuthorizationUrl());
            exit;
        } else {

            $token = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code'],
            ]);

            $client = new GuzzleHttp\Client();
            $res = $client->get('https://battlelog.co/oauth/tokeninfo/?access_token='.$token);

            if ($res->getStatusCode() == 200)
            {
                $token_info = json_decode($res->getBody());
                $user = $token_info->user;
                
                $message_extra = '';
                // See if we can scoop their discord id, too (if not already linked)
                if (isset($user->discord_id) && isset($user->discord_name))
                {
                    if(!UserDiscord::where('user_id', Auth::id())->exists())
                        UserDiscord::create([
                            'user_id' => Auth::id(),
                            'discord_id' => $user->discord_id,
                            'discord_name' => $user->discord_name
                        ]);
                }
                
                if(UserRevive::where('user_id', Auth::id())->exists())
                {
                    UserRevive::where('user_id', Auth::id())->first()->update([
                        'revive_id' => $user->id,
                        'revive_name' => $user->username,
                        'revive_email' => $user->email,
                        'revive_role' => $user->role
                    ]);
                    return redirect()->route('profile.lists')->with('success', 'We updated your Revive Network account link! '.$message_extra);
                }
                else
                {
                    UserRevive::create([
                        'user_id' => Auth::id(),
                        'revive_id' => $user->id,
                        'revive_name' => $user->username,
                        'revive_email' => $user->email,
                        'revive_role' => $user->role
                    ]);
                    return redirect()->route('profile.lists')->with('success', 'We linked your Revive Network account!'.$message_extra);
                }
            }
            else
            {
                return redirect()->back()->with('error', 'There was a critical error linking your Revive Network account!');
            }
        }
    }

    public function changePassword()
    {
        if (Hash::check(Input::get('current_password'), Auth::user()->password)) {
            if(Input::get('new_password') == Input::get('new_password_confirmed'))
            {
                //Auth::user()->update(['password', Hash::make(Input::get('new_password'))]);
                $user_id = Auth::User()->id; 
                $obj_user = User::find($user_id);
                $obj_user->password = Hash::make(Input::get('new_password'));;
                $obj_user->save();
                return redirect()->back()->with('success', 'Your password was changed!');
            }
            else
                return redirect()->back()->with('error', 'The two new passwords do not match!');
        } else {
            return redirect()->back()->with('error', 'Your current password does not match your input.');
        }
    }

    public function createHero()
    {
        if(can('game.unlimitedheroes'))
            $heroesAllowed = 1000;
        elseif(can('game.multipleheroes'))
            $heroesAllowed = 6;
        else
            $heroesAllowed = 2;

        if(GameHeroes::where('user_id', Auth::id())->count() >= $heroesAllowed)
            return redirect()->route('home')->with('error', 'You have reached the limit of ' . $heroesAllowed . ' heroes per account!');

        return view('profile.createHero');
    }

    public function doCreateHero()
    {
        if(can('game.unlimitedheroes'))
            $heroesAllowed = 1000;
        elseif(can('game.multipleheroes'))
            $heroesAllowed = 6;
        else
            $heroesAllowed = 2;

        if(GameHeroes::where('user_id', Auth::id())->count() >= $heroesAllowed)
            return redirect()->route('home')->with('error', 'You have reached the limit of ' . $heroesAllowed . ' heroes per account!');

        $validation = \Validator::make(Input::all(), [
            'nameCharacterText' => 'required|regex:/^([a-zA-Z0-9.?\-_]*)$/'
        ]);
        if ($validation->fails())
            return \Redirect::to(\URL::previous())->with('error', 'Invalid HeroName')->withInput();

        $hero = GameHeroes::create([
            'user_id' => Auth::id(),
            'heroName' => Input::get('nameCharacterText'),
            'online' => 0,
            'ip_address' => request()->ip()
        ]);

        // Level 1
        GameStats::create([
            'user_id' => Auth::id(),
            'heroID' => $hero->id,
            'statsKey' => 'level',
            'statsValue' => 1
        ]);

        // Elo 1000
        GameStats::create([
            'user_id' => Auth::id(),
            'heroID' => $hero->id,
            'statsKey' => 'elo',
            'statsValue' => 1000
        ]);

        // Faction. 1 = National, 2 = Royal
        GameStats::create([
            'user_id' => Auth::id(),
            'heroID' => $hero->id,
            'statsKey' => 'c_team',
            'statsValue' => Input::get('baseMSGFactionStats')
        ]);

        // Player Type. 0 = Commando, 1 = Soldier, 2 = Gunner
        GameStats::create([
            'user_id' => Auth::id(),
            'heroID' => $hero->id,
            'statsKey' => 'c_kit',
            'statsValue' => Input::get('baseMSGPersonaClassStats')
        ]);

        // Skin color. 1...9, 1 = darkest, 9 = lighest
        GameStats::create([
            'user_id' => Auth::id(),
            'heroID' => $hero->id,
            'statsKey' => 'c_skc',
            'statsValue' => Input::get('baseMSGAppearanceSkinToneStats')
        ]);

        // Hair color. 1...5
        GameStats::create([
            'user_id' => Auth::id(),
            'heroID' => $hero->id,
            'statsKey' => 'c_hrc',
            'statsValue' => Input::get('haircolor_ui_name')
        ]);

        // Hair Style. 0 = bald, 82...87 some hair
        GameStats::create([
            'user_id' => Auth::id(),
            'heroID' => $hero->id,
            'statsKey' => 'c_hrs',
            'statsValue' => Input::get('baseMSGAppearanceHairStyleStats')
        ]);

        // Facial Hair Style 0 = None, 102...109
        GameStats::create([
            'user_id' => Auth::id(),
            'heroID' => $hero->id,
            'statsKey' => 'c_ft',
            'statsValue' => Input::get('facial_ui_name')
        ]);

        return redirect()->route('home')->with('success', 'Your hero was created!');
    }

    public function createHeroAvailability()
    {
        if(GameHeroes::where('heroName', Input::get('heroName'))->exists())
            return 'This username is already taken!';
    }

    public function answerAll()
    {
        foreach (Auth::user()->friendRequests as $request)
            Auth::user()->friendRequestAnswer($request->sender, Input::get('answer'));

        return redirect()->back()->with('success', Input::get('answer') == 'accepted' ? 'You accepted the all friend requests!' : 'You declined the all friend requests!');
    }
}
