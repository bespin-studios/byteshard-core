<?php

namespace byteShard\Authentication;

enum JWTProperties: string
{
    case Firstname = 'given_name';
    case Lastname  = 'family_name';
    case Email     = 'email';
    case Groups    = 'groups';
    case Username  = 'preferred_username';

}
